<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $startDate = $validated['start_date']
            ?? now()->startOfMonth()->toDateString();

        $endDate = $validated['end_date']
            ?? now()->endOfMonth()->toDateString();

        $invoiceQuery = Invoice::with('customer')
            ->whereBetween('due_date', [$startDate, $endDate]);

        $paymentQuery = Payment::where('status', 'verified')
            ->whereBetween('payment_date', [$startDate, $endDate]);

        $billableInvoiceQuery = (clone $invoiceQuery)
            ->where('status', '!=', 'cancelled');

        $cancelledInvoiceQuery = (clone $invoiceQuery)
            ->where('status', 'cancelled');

        $summary = [
            'totalInvoices' => (clone $billableInvoiceQuery)->count(),

            'paidInvoices' => (clone $billableInvoiceQuery)
                ->where('status', 'paid')
                ->count(),

            'unpaidInvoices' => (clone $billableInvoiceQuery)
                ->where('status', 'unpaid')
                ->count(),

            'overdueInvoices' => (clone $billableInvoiceQuery)
                ->where('status', 'overdue')
                ->count(),

            'cancelledInvoices' => $cancelledInvoiceQuery->count(),

            'verifiedPayments' => (clone $paymentQuery)->count(),

            'totalPaymentAmount' => (clone $paymentQuery)->sum('amount'),

            'totalInvoiceAmount' => (clone $billableInvoiceQuery)
                ->where('status', 'paid')
                ->sum('total_amount'),
        ];

        $invoices = $invoiceQuery
            ->latest('due_date')
            ->paginate(15)
            ->withQueryString();

        return view('reports.index', compact(
            'summary',
            'invoices',
            'startDate',
            'endDate'
        ));
    }

    public function exportCsv(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $startDate = $validated['start_date']
            ?? now()->startOfMonth()->toDateString();

        $endDate = $validated['end_date']
            ?? now()->endOfMonth()->toDateString();

        $invoices = Invoice::with('customer')
            ->whereBetween('due_date', [$startDate, $endDate])
            ->orderBy('due_date')
            ->get();

        $fileName = 'laporan-billing-'.$startDate.'-sampai-'.$endDate.'.csv';

        return response()->streamDownload(function () use ($invoices) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Nomor Invoice',
                'Pelanggan',
                'Tanggal Jatuh Tempo',
                'Total Invoice',
                'Status',
            ]);

            foreach ($invoices as $invoice) {
                fputcsv($handle, [
                    $invoice->invoice_number,
                    $invoice->customer->name ?? '-',
                    optional($invoice->due_date)->format('Y-m-d') ?? '-',
                    $invoice->total_amount,
                    $invoice->status,
                ]);
            }

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
