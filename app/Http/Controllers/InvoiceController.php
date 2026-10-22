<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Notifications\AsnetNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class InvoiceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'status' => 'nullable|in:unpaid,paid,overdue,cancelled',
            'due_from' => 'nullable|date',
            'due_to' => 'nullable|date|after_or_equal:due_from',
        ]);

        $search = trim((string) ($validated['q'] ?? ''));
        $status = $validated['status'] ?? '';
        $dueFrom = $validated['due_from'] ?? '';
        $dueTo = $validated['due_to'] ?? '';

        $invoices = Invoice::query()
            ->with('customer')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
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
            ->when($dueFrom !== '', fn ($query) => $query->whereDate('due_date', '>=', $dueFrom))
            ->when($dueTo !== '', fn ($query) => $query->whereDate('due_date', '<=', $dueTo))
            ->latest('due_date')
            ->paginate(15)
            ->withQueryString();

        $invoiceStats = [
            'total' => Invoice::sum('total_amount'),
            'paid' => Invoice::where('status', 'paid')->sum('total_amount'),
            'unpaid' => Invoice::where('status', 'unpaid')->sum('total_amount'),
            'overdue' => Invoice::where('status', 'overdue')->sum('total_amount'),
        ];

        return view('invoices.index', compact(
            'invoices',
            'search',
            'status',
            'dueFrom',
            'dueTo',
            'invoiceStats'
        ));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $customers = Customer::with('internetPackage')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        return view('invoices.create', compact('customers'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => [
                'required',
                Rule::exists('customers', 'id')
                    ->where('status', 'active')
                    ->whereNull('deleted_at'),
            ],
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
            'due_date' => 'required|date|after_or_equal:period_end',
            'total_amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $validated['invoice_number'] =
            'INV-'.now()->format('Ym').'-'.Str::upper(Str::random(8));

        $validated['status'] = 'unpaid';

        $invoice = Invoice::create($validated);
        $invoice->load('customer.user');

        $invoice->customer?->user?->notify(new AsnetNotification(
            'Invoice baru tersedia',
            "Invoice {$invoice->invoice_number} sebesar Rp ".
                number_format((float) $invoice->total_amount, 0, ',', '.').
                ' telah dibuat.',
            route('customer.invoices.show', $invoice),
            'info'
        ));

        return redirect()
            ->route('invoices.index')
            ->with('success', 'Invoice berhasil dibuat.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Invoice $invoice)
    {
        $invoice->load([
            'customer.internetPackage',
            'payments.verifier',
        ]);

        return view('invoices.show', compact('invoice'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Invoice $invoice)
    {
        return view('invoices.edit', compact('invoice'));
    }

    public function cancel(Invoice $invoice)
    {
        if (in_array($invoice->status, ['paid', 'cancelled'])) {
            return redirect()
                ->route('invoices.show', $invoice)
                ->with(
                    'error',
                    'Invoice yang sudah paid atau cancelled tidak dapat dibatalkan.'
                );
        }

        $hasPayment = $invoice->payments()
            ->whereIn('status', ['pending', 'verified'])
            ->exists();

        if ($hasPayment) {
            return redirect()
                ->route('invoices.show', $invoice)
                ->with(
                    'error',
                    'Invoice tidak dapat dibatalkan karena sudah memiliki pembayaran.'
                );
        }

        $invoice->update([
            'status' => 'cancelled',
        ]);

        return redirect()
            ->route('invoices.show', $invoice)
            ->with('success', 'Invoice berhasil dibatalkan.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Invoice $invoice)
    {
        if (in_array($invoice->status, ['paid', 'cancelled'])) {
            return redirect()
                ->route('invoices.show', $invoice)
                ->with(
                    'error',
                    'Invoice yang sudah paid atau cancelled tidak boleh diedit.'
                );
        }

        $validated = $request->validate([
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
            'due_date' => 'required|date|after_or_equal:period_end',
            'total_amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $invoice->update([
            'period_start' => $validated['period_start'],
            'period_end' => $validated['period_end'],
            'due_date' => $validated['due_date'],
            'total_amount' => $validated['total_amount'],
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()
            ->route('invoices.show', $invoice)
            ->with('success', 'Invoice berhasil diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
