<x-app-layout>
    <div class="module-page invoice-module-page">
        <div class="module-page-heading"><h1>Manajemen Invoice</h1><a href="{{ route('invoices.create') }}" class="reference-primary-action"><span>+</span> Buat Invoice</a></div>
        <div class="module-stat-grid">
            <div class="module-stat-card"><span class="module-stat-icon blue"><img src="{{ asset('images/icons/invoice.svg') }}" alt=""></span><div><small>Total Tagihan</small><strong>Rp {{ number_format((float) $invoiceStats['total'], 0, ',', '.') }}</strong></div></div>
            <div class="module-stat-card"><span class="module-stat-icon green"><img src="{{ asset('images/icons/check.svg') }}" alt=""></span><div><small>Lunas</small><strong>Rp {{ number_format((float) $invoiceStats['paid'], 0, ',', '.') }}</strong></div></div>
            <div class="module-stat-card"><span class="module-stat-icon orange"><img src="{{ asset('images/icons/alert.svg') }}" alt=""></span><div><small>Belum Bayar</small><strong>Rp {{ number_format((float) $invoiceStats['unpaid'], 0, ',', '.') }}</strong></div></div>
            <div class="module-stat-card"><span class="module-stat-icon red"><img src="{{ asset('images/icons/alert.svg') }}" alt=""></span><div><small>Terlambat</small><strong>Rp {{ number_format((float) $invoiceStats['overdue'], 0, ',', '.') }}</strong></div></div>
        </div>
        <div class="module-table-card">
            <form method="GET" action="{{ route('invoices.index') }}" class="module-filter-row">
                <label class="module-search-field"><img src="{{ asset('images/icons/search.svg') }}" alt=""><input type="search" name="q" value="{{ $search }}" placeholder="Cari nomor invoice, pelanggan..."></label>
                <label class="module-date-field"><img src="{{ asset('images/icons/calendar.svg') }}" alt=""><input type="date" name="due_from" value="{{ $dueFrom }}"><span>–</span><input type="date" name="due_to" value="{{ $dueTo }}"></label>
                <select name="status"><option value="">Semua Status</option><option value="paid" @selected($status === 'paid')>Lunas</option><option value="unpaid" @selected($status === 'unpaid')>Belum Bayar</option><option value="overdue" @selected($status === 'overdue')>Terlambat</option><option value="cancelled" @selected($status === 'cancelled')>Dibatalkan</option></select>
                <button type="submit" class="module-filter-button">Filter</button>
            </form>
            <div class="module-table-scroll"><table class="module-reference-table"><thead><tr><th>Nomor Invoice</th><th>Pelanggan</th><th>Periode Tagihan</th><th>Jatuh Tempo</th><th>Jumlah</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
                @forelse ($invoices as $invoice)
                    <tr><td>{{ $invoice->invoice_number }}</td><td>{{ $invoice->customer->name ?? '-' }}</td><td>{{ optional($invoice->period_start)->format('M Y') }}</td><td>{{ optional($invoice->due_date)->format('d/m/Y') }}</td><td>Rp {{ number_format((float) $invoice->total_amount, 0, ',', '.') }}</td><td><span class="status-badge status-{{ $invoice->status }}">{{ match($invoice->status) {'paid' => 'Lunas', 'unpaid' => 'Belum Bayar', 'overdue' => 'Terlambat', 'cancelled' => 'Dibatalkan', default => $invoice->status} }}</span></td><td><a href="{{ route('invoices.show', $invoice) }}" class="row-more">•••</a></td></tr>
                @empty
                    <tr><td colspan="7" class="empty-table">Belum ada data invoice.</td></tr>
                @endforelse
            </tbody></table></div>
            <div class="module-table-footer"><span>Menampilkan {{ $invoices->firstItem() ?? 0 }}-{{ $invoices->lastItem() ?? 0 }} dari {{ $invoices->total() }} data</span>{{ $invoices->links() }}</div>
        </div>
    </div>
</x-app-layout>
