<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\AsnetNotification;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CustomerPortalController extends Controller
{
    public function invoices()
    {
        $customer = $this->customer();

        $invoices = $customer->invoices()
            ->latest('due_date')
            ->paginate(10);

        return view('customer.invoices.index', compact('customer', 'invoices'));
    }

    public function invoice(Invoice $invoice)
    {
        $customer = $this->customer();

        abort_unless($invoice->customer_id === $customer->id, 404);

        $invoice->load([
            'customer.internetPackage',
            'payments' => fn ($query) => $query->latest(),
        ]);

        return view('customer.invoices.show', compact('customer', 'invoice'));
    }

    public function payments()
    {
        $customer = $this->customer();

        $payments = Payment::query()
            ->with('invoice')
            ->whereHas('invoice', fn ($query) => $query->where('customer_id', $customer->id))
            ->latest()
            ->paginate(10);

        return view('customer.payments.index', compact('customer', 'payments'));
    }

    public function createPayment(Request $request)
    {
        $customer = $this->customer();
        $invoiceId = $request->integer('invoice_id');

        $invoices = $customer->invoices()
            ->whereIn('status', ['unpaid', 'overdue'])
            ->whereDoesntHave('payments', fn ($query) => $query->where('status', 'pending'))
            ->latest('due_date')
            ->get();

        return view('customer.payments.create', compact('customer', 'invoices', 'invoiceId'));
    }

    public function storePayment(Request $request, AuditLogService $auditLog)
    {
        $customer = $this->customer();

        $validated = $request->validate([
            'invoice_id' => [
                'required',
                'integer',
                Rule::exists('invoices', 'id')
                    ->where('customer_id', $customer->id)
                    ->whereIn('status', ['unpaid', 'overdue']),
            ],
            'method' => 'required|in:bank_transfer,e_wallet,qris',
            'proof_path' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
            'notes' => 'nullable|string|max:2000',
        ]);

        $qrisCreatedAt = null;
        $qrisReference = null;
        $qrisExpiresAt = null;

        if ($validated['method'] === 'qris') {
            $qrisImagePath = config('payments.qris.image_path');

            if (
                ! config('payments.qris.enabled')
                || ! is_string($qrisImagePath)
                || ! is_file(public_path($qrisImagePath))
            ) {
                return back()
                    ->withErrors([
                        'method' => 'QRIS belum tersedia. Silakan gunakan transfer bank atau e-wallet.',
                    ])
                    ->withInput();
            }

            $qrisCreatedAt = now();
            $qrisReference = 'QRIS-'.$qrisCreatedAt->format('YmdHis').'-'.Str::upper(Str::random(6));
            $qrisExpiresAt = $qrisCreatedAt->copy()->addHours(
                max(1, (int) config('payments.qris.expiration_hours', 24))
            );
        }

        $proofPath = null;

        if ($request->hasFile('proof_path')) {
            $proofPath = $request->file('proof_path')->store('payment-proofs', 'private');
        }

        try {
            $payment = DB::transaction(function () use (
                $customer,
                $validated,
                $proofPath,
                $qrisReference,
                $qrisCreatedAt,
                $qrisExpiresAt
            ) {
                $invoice = Invoice::query()
                    ->where('customer_id', $customer->id)
                    ->lockForUpdate()
                    ->findOrFail($validated['invoice_id']);

                if ($invoice->payments()->where('status', 'pending')->exists()) {
                    throw ValidationException::withMessages([
                        'invoice_id' => 'Invoice ini sudah memiliki pembayaran yang menunggu verifikasi.',
                    ]);
                }

                return Payment::create([
                    'invoice_id' => $invoice->id,
                    'payment_date' => today(),
                    'amount' => $invoice->total_amount,
                    'method' => $validated['method'],
                    'qris_reference' => $qrisReference,
                    'qris_created_at' => $qrisCreatedAt,
                    'qris_expires_at' => $qrisExpiresAt,
                    'proof_path' => $proofPath,
                    'status' => 'pending',
                    'verified_by' => null,
                    'verified_at' => null,
                    'notes' => $validated['notes'] ?? null,
                ]);
            });
        } catch (ValidationException $exception) {
            if ($proofPath) {
                Storage::disk('private')->delete($proofPath);
            }

            throw $exception;
        } catch (\Throwable $exception) {
            if ($proofPath) {
                Storage::disk('private')->delete($proofPath);
            }

            report($exception);

            throw ValidationException::withMessages([
                'payment' => 'Pembayaran gagal disimpan. Silakan coba lagi.',
            ]);
        }

        $auditLog->record(
            'customer_payment.created',
            $payment,
            'Pelanggan mengajukan pembayaran.',
            [
                'invoice_id' => $payment->invoice_id,
                'amount' => $payment->amount,
                'method' => $payment->method,
            ]
        );

        User::query()
            ->whereIn('role', ['owner', 'admin'])
            ->where('active', true)
            ->get()
            ->each(fn (User $user) => $user->notify(new AsnetNotification(
                'Pembayaran baru menunggu verifikasi',
                "{$customer->name} mengajukan pembayaran sebesar Rp ".
                    number_format((float) $payment->amount, 0, ',', '.').'.',
                route('payments.show', $payment),
                'warning'
            )));

        return redirect()
            ->route('customer.payments.show', $payment)
            ->with('success', 'Permintaan pembayaran berhasil dibuat dan menunggu verifikasi.');
    }

    public function payment(Payment $payment)
    {
        $customer = $this->customer();

        $payment->load('invoice.customer');

        abort_unless($payment->invoice?->customer_id === $customer->id, 404);

        return view('customer.payments.show', compact('customer', 'payment'));
    }

    public function proof(Payment $payment)
    {
        $customer = $this->customer();

        $payment->load('invoice');

        abort_unless($payment->invoice?->customer_id === $customer->id, 404);
        abort_unless($payment->proof_path, 404);

        $disk = Storage::disk('private');
        abort_unless($disk->exists($payment->proof_path), 404);

        return $disk->download($payment->proof_path, basename($payment->proof_path));
    }

    private function customer(): Customer
    {
        $customer = Auth::user()->customer;

        abort_unless($customer, 403, 'Akun pelanggan belum terhubung ke data pelanggan.');

        return $customer;
    }
}
