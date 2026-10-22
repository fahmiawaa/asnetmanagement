<x-app-layout>
    <div class="module-page payment-module-page">
        <div class="module-page-heading"><h1>Verifikasi Pembayaran</h1><a href="{{ route('payments.create') }}" class="reference-primary-action"><span>+</span> Tambah Pembayaran</a></div>
        <div class="module-stat-grid">
            <div class="module-stat-card"><span class="module-stat-icon blue"><img src="{{ asset('images/icons/payment.svg') }}" alt=""></span><div><small>Menunggu Verifikasi</small><strong>Rp {{ number_format((float) $paymentStats['pending'], 0, ',', '.') }}</strong></div></div>
            <div class="module-stat-card"><span class="module-stat-icon green"><img src="{{ asset('images/icons/check.svg') }}" alt=""></span><div><small>Terverifikasi</small><strong>Rp {{ number_format((float) $paymentStats['verified'], 0, ',', '.') }}</strong></div></div>
            <div class="module-stat-card"><span class="module-stat-icon red"><img src="{{ asset('images/icons/alert.svg') }}" alt=""></span><div><small>Ditolak</small><strong>Rp {{ number_format((float) $paymentStats['rejected'], 0, ',', '.') }}</strong></div></div>
            <div class="module-stat-card"><span class="module-stat-icon orange"><img src="{{ asset('images/icons/clock.svg') }}" alt=""></span><div><small>Kedaluwarsa</small><strong>Rp {{ number_format((float) $paymentStats['expired'], 0, ',', '.') }}</strong></div></div>
        </div>
        <div class="module-table-card">
            <form method="GET" action="{{ route('payments.index') }}" class="module-filter-row">
                <label class="module-search-field"><img src="{{ asset('images/icons/search.svg') }}" alt=""><input type="search" name="q" value="{{ $search }}" placeholder="Cari invoice, pelanggan..."></label>
                <select name="method"><option value="">Semua Metode</option><option value="qris" @selected($method === 'qris')>QRIS</option><option value="bank_transfer" @selected($method === 'bank_transfer')>Transfer Bank</option><option value="cash" @selected($method === 'cash')>Tunai</option><option value="e_wallet" @selected($method === 'e_wallet')>E-Wallet</option></select>
                <select name="status"><option value="">Semua Status</option><option value="pending" @selected($status === 'pending')>Menunggu</option><option value="verified" @selected($status === 'verified')>Terverifikasi</option><option value="rejected" @selected($status === 'rejected')>Ditolak</option><option value="expired" @selected($status === 'expired')>Kedaluwarsa</option></select>
                <button type="submit" class="module-filter-button">Filter</button>
            </form>
            <div class="module-table-scroll"><table class="module-reference-table"><thead><tr><th>Invoice</th><th>Pelanggan</th><th>Tanggal</th><th>Metode</th><th>Jumlah</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
                @forelse ($payments as $payment)
                    <tr><td>{{ $payment->invoice->invoice_number ?? '-' }}</td><td>{{ $payment->invoice->customer->name ?? '-' }}</td><td>{{ optional($payment->payment_date)->format('d/m/Y') ?? '-' }}</td><td>{{ strtoupper($payment->method) }}</td><td>Rp {{ number_format((float) $payment->amount, 0, ',', '.') }}</td><td><span class="status-badge status-{{ $payment->status }}">{{ match($payment->status) {'verified' => 'Terverifikasi', 'rejected' => 'Ditolak', 'expired' => 'Kedaluwarsa', default => 'Menunggu'} }}</span></td><td><a href="{{ route('payments.show', $payment) }}" class="row-more">•••</a></td></tr>
                @empty
                    <tr><td colspan="7" class="empty-table">Belum ada data pembayaran.</td></tr>
                @endforelse
            </tbody></table></div>
            <div class="module-table-footer"><span>Menampilkan {{ $payments->firstItem() ?? 0 }}-{{ $payments->lastItem() ?? 0 }} dari {{ $payments->total() }} data</span>{{ $payments->links() }}</div>
        </div>
    </div>
</x-app-layout>
