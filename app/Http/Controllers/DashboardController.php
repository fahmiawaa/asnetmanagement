<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        if (Auth::user()->role === 'pelanggan') {
            $customer = Auth::user()->customer?->load(['internetPackage', 'pppoeAccount']);

            abort_unless($customer, 403, 'Akun pelanggan belum terhubung ke data pelanggan.');

            $invoices = $customer->invoices()
                ->latest('due_date')
                ->limit(5)
                ->get();

            $currentInvoice = $customer->invoices()
                ->whereIn('status', ['unpaid', 'overdue'])
                ->orderBy('due_date')
                ->first();

            $payments = Payment::query()
                ->with('invoice')
                ->whereHas('invoice', fn ($query) => $query->where('customer_id', $customer->id))
                ->latest('payment_date')
                ->limit(5)
                ->get();

            $outstandingAmount = $customer->invoices()
                ->whereIn('status', ['unpaid', 'overdue'])
                ->sum('total_amount');

            return view('customer.dashboard', compact(
                'customer',
                'invoices',
                'currentInvoice',
                'payments',
                'outstandingAmount'
            ));
        }

        $stats = [
            'activeCustomers' => Customer::where('status', 'active')->count(),
            'totalCustomers' => Customer::count(),
            'unpaidInvoices' => Invoice::where('status', 'unpaid')->count(),
            'overdueInvoices' => Invoice::where('status', 'overdue')->count(),
            'verifiedPayments' => Payment::where('status', 'verified')
                ->sum('amount'),
            'isolatedCustomers' => Customer::where('status', 'isolated')->count(),
            'pendingPaymentAmount' => Invoice::whereIn('status', ['unpaid', 'overdue'])
                ->sum('total_amount'),
        ];

        $revenueByMonth = collect(range(5, 0))
            ->map(function (int $monthsAgo): array {
                $month = now()->subMonths($monthsAgo);

                return [
                    'label' => $month->translatedFormat('M'),
                    'amount' => (float) Payment::query()
                        ->where('status', 'verified')
                        ->whereBetween('payment_date', [
                            $month->copy()->startOfMonth()->toDateString(),
                            $month->copy()->endOfMonth()->toDateString(),
                        ])
                        ->sum('amount'),
                ];
            })
            ->push([
                'label' => now()->translatedFormat('M'),
                'amount' => (float) Payment::query()
                    ->where('status', 'verified')
                    ->whereBetween('payment_date', [
                        now()->startOfMonth()->toDateString(),
                        now()->endOfMonth()->toDateString(),
                    ])
                    ->sum('amount'),
            ]);

        $maxRevenue = max((float) $revenueByMonth->max('amount'), 1);

        $recentPayments = Payment::query()
            ->with('invoice.customer')
            ->where('status', 'verified')
            ->latest('verified_at')
            ->limit(4)
            ->get();

        $priorityCustomers = Customer::query()
            ->with([
                'internetPackage',
                'invoices' => fn ($query) => $query->whereIn('status', ['unpaid', 'overdue']),
            ])
            ->whereIn('status', ['overdue', 'isolated'])
            ->latest()
            ->limit(5)
            ->get();

        return view('dashboard', compact(
            'stats',
            'revenueByMonth',
            'maxRevenue',
            'recentPayments',
            'priorityCustomers'
        ));
    }
}
