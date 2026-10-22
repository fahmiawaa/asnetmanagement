<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\IsolationLog;
use App\Models\Payment;
use App\Notifications\AsnetNotification;
use App\Services\AuditLogService;
use App\Services\RestoreCustomerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        Payment::query()
            ->where('method', 'qris')
            ->where('status', 'pending')
            ->whereNotNull('qris_expires_at')
            ->where('qris_expires_at', '<=', now())
            ->update(['status' => 'expired']);

        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'status' => 'nullable|in:pending,verified,rejected,expired',
            'method' => 'nullable|in:cash,bank_transfer,e_wallet,qris',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        $search = trim((string) ($validated['q'] ?? ''));
        $status = $validated['status'] ?? '';
        $method = $validated['method'] ?? '';
        $dateFrom = $validated['date_from'] ?? '';
        $dateTo = $validated['date_to'] ?? '';

        $payments = Payment::query()
            ->with(['invoice.customer', 'verifier'])
            ->when($search !== '', function ($query) use ($search) {
                $query->whereHas('invoice', function ($query) use ($search) {
                    $query
                        ->where('invoice_number', 'like', "%{$search}%")
                        ->orWhereHas('customer', function ($query) use ($search) {
                            $query
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('customer_code', 'like', "%{$search}%");
                        });
                });
            })
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($method !== '', fn ($query) => $query->where('method', $method))
            ->when($dateFrom !== '', fn ($query) => $query->whereDate('payment_date', '>=', $dateFrom))
            ->when($dateTo !== '', fn ($query) => $query->whereDate('payment_date', '<=', $dateTo))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $paymentStats = [
            'pending' => Payment::where('status', 'pending')->sum('amount'),
            'verified' => Payment::where('status', 'verified')->sum('amount'),
            'rejected' => Payment::where('status', 'rejected')->sum('amount'),
            'expired' => Payment::where('status', 'expired')->sum('amount'),
        ];

        return view('payments.index', compact(
            'payments',
            'search',
            'status',
            'method',
            'dateFrom',
            'dateTo',
            'paymentStats'
        ));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $invoices = Invoice::with('customer')
            ->whereIn('status', ['unpaid', 'overdue'])
            ->orderBy('due_date')
            ->get();

        return view('payments.create', compact('invoices'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, AuditLogService $auditLog)
    {
        $validated = $request->validate([
            'invoice_id' => [
                'required',
                Rule::exists('invoices', 'id')
                    ->whereIn('status', ['unpaid', 'overdue']),
            ],
            'payment_date' => 'required|date|before_or_equal:today',
            'amount' => 'required|numeric|gt:0',
            'method' => 'required|in:cash,bank_transfer,e_wallet,qris',
            'proof_path' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
            'notes' => 'nullable|string',
        ]);

        $invoice = Invoice::findOrFail($validated['invoice_id']);

        if (in_array($invoice->status, ['paid', 'cancelled'])) {
            return back()
                ->withErrors([
                    'invoice_id' => 'Invoice ini sudah lunas atau dibatalkan.',
                ])
                ->withInput();
        }

        if ($invoice->payments()->where('status', 'pending')->exists()) {
            return back()
                ->withErrors([
                    'invoice_id' => 'Invoice sudah memiliki pembayaran yang menunggu verifikasi.',
                ])
                ->withInput();
        }

        if ($validated['method'] === 'qris') {
            $qrisImagePath = config('payments.qris.image_path');

            if (
                ! config('payments.qris.enabled')
                || ! is_string($qrisImagePath)
                || ! is_file(public_path($qrisImagePath))
            ) {
                return back()
                    ->withErrors([
                        'method' => 'QRIS belum dikonfigurasi. Aktifkan QRIS dan letakkan gambar QRIS merchant pada path yang ditentukan.',
                    ])
                    ->withInput();
            }

            $qrisCreatedAt = now();
            $validated['qris_reference'] =
                'QRIS-'.$qrisCreatedAt->format('YmdHis').'-'.Str::upper(Str::random(6));
            $validated['qris_created_at'] = $qrisCreatedAt;
            $validated['qris_expires_at'] = $qrisCreatedAt->copy()->addHours(
                max(1, (int) config('payments.qris.expiration_hours', 24))
            );
        } else {
            $validated['qris_reference'] = null;
            $validated['qris_created_at'] = null;
            $validated['qris_expires_at'] = null;
        }

        if ($request->hasFile('proof_path')) {
            $validated['proof_path'] = $request
                ->file('proof_path')
                ->store('payment-proofs', 'private');
        } else {
            $validated['proof_path'] = null;
        }

        $validated['status'] = 'pending';
        $validated['verified_by'] = null;
        $validated['verified_at'] = null;

        $payment = Payment::create($validated);
        $invoice->load('customer.user');

        $invoice->customer?->user?->notify(new AsnetNotification(
            'Pembayaran tercatat',
            "Pembayaran untuk {$invoice->invoice_number} menunggu verifikasi.",
            route('payments.show', $payment),
            'info'
        ));

        $auditLog->record(
            'payment.created',
            $payment,
            'Pembayaran baru dicatat dan menunggu verifikasi.',
            [
                'invoice_id' => $payment->invoice_id,
                'amount' => $payment->amount,
                'method' => $payment->method,
            ]
        );

        return redirect()
            ->route('payments.index')
            ->with('success', 'Pembayaran berhasil dicatat dan menunggu verifikasi.');
    }

    public function reject(Payment $payment, AuditLogService $auditLog)
    {
        try {
            DB::transaction(function () use ($payment): void {
                $lockedPayment = Payment::query()
                    ->lockForUpdate()
                    ->findOrFail($payment->id);

                if ($lockedPayment->status !== 'pending') {
                    throw ValidationException::withMessages([
                        'payment' => 'Pembayaran yang sudah diproses tidak dapat ditolak lagi.',
                    ]);
                }

                $lockedPayment->update([
                    'status' => 'rejected',
                    'verified_by' => auth()->id(),
                    'verified_at' => now(),
                ]);
            });
        } catch (ValidationException $exception) {
            return redirect()
                ->route('payments.index')
                ->withErrors($exception->errors());
        }

        $payment->refresh();
        $payment->load('invoice.customer.user');
        $payment->invoice?->customer?->user?->notify(new AsnetNotification(
            'Pembayaran ditolak',
            "Pembayaran untuk {$payment->invoice->invoice_number} ditolak. Silakan cek detail pembayaran.",
            route('customer.payments.show', $payment),
            'danger'
        ));
        $auditLog->record(
            'payment.rejected',
            $payment,
            'Pembayaran ditolak oleh pengelola.'
        );

        return redirect()
            ->route('payments.index')
            ->with(
                'success',
                'Pembayaran berhasil ditolak. Invoice tetap belum lunas.'
            );
    }

    /**
     * Display the specified resource.
     */
    public function show(Payment $payment)
    {
        $payment->load([
            'invoice.customer',
            'verifier',
        ]);

        return view('payments.show', compact('payment'));
    }

    public function proof(Payment $payment)
    {
        abort_unless($payment->proof_path, 404);

        $disk = Storage::disk('private');

        abort_unless($disk->exists($payment->proof_path), 404);

        return $disk->download(
            $payment->proof_path,
            basename($payment->proof_path)
        );
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public function verify(
        Payment $payment,
        RestoreCustomerService $restoreService,
        AuditLogService $auditLog
    ) {
        $invoice = DB::transaction(function () use ($payment) {
            $lockedPayment = Payment::query()
                ->lockForUpdate()
                ->findOrFail($payment->id);

            if ($lockedPayment->status !== 'pending') {
                throw ValidationException::withMessages([
                    'payment' => 'Pembayaran ini sudah diproses sebelumnya.',
                ]);
            }

            if (
                $lockedPayment->method === 'qris'
                && $lockedPayment->qris_expires_at
                && now()->greaterThan($lockedPayment->qris_expires_at)
            ) {
                $lockedPayment->update([
                    'status' => 'expired',
                ]);

                throw ValidationException::withMessages([
                    'payment' => 'Sesi QRIS sudah kedaluwarsa. Buat permintaan QRIS baru selama 24 jam.',
                ]);
            }

            $lockedInvoice = Invoice::query()
                ->lockForUpdate()
                ->findOrFail($lockedPayment->invoice_id);

            $isolationIsProcessing = IsolationLog::query()
                ->where('invoice_id', $lockedInvoice->id)
                ->where('action', 'isolate')
                ->where('approval_status', 'processing')
                ->lockForUpdate()
                ->exists();

            if ($isolationIsProcessing) {
                throw ValidationException::withMessages([
                    'payment' => 'Auto-isolir sedang memproses pelanggan ini. Coba verifikasi kembali beberapa saat lagi.',
                ]);
            }

            if (in_array($lockedInvoice->status, ['paid', 'cancelled'], true)) {
                throw ValidationException::withMessages([
                    'payment' => 'Invoice sudah lunas atau dibatalkan.',
                ]);
            }

            $hasVerifiedPayment = Payment::query()
                ->where('invoice_id', $lockedInvoice->id)
                ->where('status', 'verified')
                ->exists();

            if ($hasVerifiedPayment) {
                throw ValidationException::withMessages([
                    'payment' => 'Invoice sudah memiliki pembayaran terverifikasi.',
                ]);
            }

            if (
                round((float) $lockedPayment->amount, 2)
                !== round((float) $lockedInvoice->total_amount, 2)
            ) {
                throw ValidationException::withMessages([
                    'payment' => 'Nominal pembayaran tidak sama dengan total invoice.',
                ]);
            }

            $lockedPayment->update([
                'status' => 'verified',
                'verified_by' => auth()->id(),
                'verified_at' => now(),
            ]);

            $lockedInvoice->update([
                'status' => 'paid',
                'paid_at' => now(),
            ]);

            return $lockedInvoice->fresh([
                'customer.pppoeAccount',
                'customer.user',
            ]);
        });

        $customer = $invoice->customer;
        $customer->user?->notify(new AsnetNotification(
            'Pembayaran terverifikasi',
            "Pembayaran untuk {$invoice->invoice_number} berhasil diverifikasi. Layanan Anda diperbarui.",
            route('customer.payments.show', $payment),
            'success'
        ));

        $hasOverdueInvoice = $customer
            ->invoices()
            ->where('status', 'overdue')
            ->exists();

        $restoreResult = null;

        if (! $hasOverdueInvoice) {
            if ($customer->status === 'isolated' && $customer->pppoe_account_id) {
                $restoreLog = IsolationLog::where([
                    'customer_id' => $customer->id,
                    'invoice_id' => $invoice->id,
                    'action' => 'restore',
                ])->latest()->first();

                if (! $restoreLog) {
                    $restoreLog = IsolationLog::create([
                        'customer_id' => $customer->id,
                        'invoice_id' => $invoice->id,
                        'action' => 'restore',
                        'old_status' => 'isolated',
                        'new_status' => 'active',
                        'execution_mode' => 'simulation',
                        'approval_status' => 'pending',
                        'reason' => 'Payment verified dan tidak ada invoice overdue.',
                        'notes' => 'Restore dijalankan setelah pembayaran diverifikasi; customer tetap isolated sampai live verification berhasil.',
                    ]);
                }

                if ($restoreLog->approval_status !== 'executed') {
                    $restoreResult = $restoreService->execute($restoreLog);
                }
            } elseif (
                $customer->status === 'overdue'
                && (
                    ! $customer->pppoeAccount
                    || (
                        ! $customer->pppoeAccount->disabled
                        && $customer->pppoeAccount->is_present
                    )
                )
            ) {
                $customer->update([
                    'status' => 'active',
                ]);
            }
        }

        $message = 'Pembayaran berhasil diverifikasi dan invoice menjadi lunas.';

        if ($restoreResult && $restoreResult['success']) {
            $message .= ' Layanan berhasil dipulihkan setelah verifikasi live MikroTik.';

            if ($restoreResult['whatsapp_url']) {
                $message .= ' Link WhatsApp restore: '.$restoreResult['whatsapp_url'];
            }
        } elseif ($restoreResult) {
            $message .= ' '.$restoreResult['message'];
        }

        $payment->refresh();
        $auditLog->record(
            'payment.verified',
            $payment,
            'Pembayaran diverifikasi dan invoice ditandai lunas.',
            [
                'invoice_id' => $invoice->id,
                'customer_id' => $invoice->customer_id,
                'restore_success' => (bool) ($restoreResult['success'] ?? false),
            ]
        );

        return redirect()
            ->route('payments.index')
            ->with('success', $message);
    }
}
