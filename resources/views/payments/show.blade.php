<x-app-layout>
    @php
        $qrisImagePath = config('payments.qris.image_path');
        $qrisImageExists = is_string($qrisImagePath) && is_file(public_path($qrisImagePath));
        $qrisExpired = $payment->qris_expires_at && now()->greaterThan($payment->qris_expires_at);
        $methodLabels = ['cash' => 'Tunai', 'bank_transfer' => 'Transfer Bank', 'e_wallet' => 'E-Wallet', 'qris' => 'QRIS'];
    @endphp
    <div class="payment-verification-page">
        <div class="module-page-heading"><h1>Verifikasi Pembayaran</h1><a href="{{ route('payments.index') }}" class="plain-back-link">‹ Kembali</a></div>
        <div class="payment-verification-card">
            <section class="payment-detail-panel">
                <h2>Detail Pembayaran</h2>
                <div class="payment-detail-list">
                    <div><span class="detail-icon"><img src="{{ asset('images/icons/customers.svg') }}" alt=""></span><p>Pelanggan<strong>{{ $payment->invoice->customer->name ?? '-' }}</strong></p></div>
                    <div><span class="detail-icon"><img src="{{ asset('images/icons/invoice.svg') }}" alt=""></span><p>No. Faktur<strong>{{ $payment->invoice->invoice_number ?? '-' }}</strong></p></div>
                    <div><span class="detail-icon"><img src="{{ asset('images/icons/payment.svg') }}" alt=""></span><p>Total Tagihan<strong>Rp {{ number_format((float) $payment->amount, 0, ',', '.') }}</strong></p></div>
                    <div><span class="detail-icon"><img src="{{ asset('images/icons/qr.svg') }}" alt=""></span><p>Metode Pembayaran<strong>{{ $methodLabels[$payment->method] ?? strtoupper($payment->method) }}</strong></p></div>
                </div>
                <a href="{{ route('invoices.show', $payment->invoice) }}" class="payment-invoice-link">Lihat Invoice</a>
            </section>
            <section class="payment-qr-panel">
                @if ($payment->method === 'qris' && $qrisImageExists && !$qrisExpired)
                    <h2>Kode QRIS</h2><img src="{{ asset($qrisImagePath) }}" class="payment-qr-image" alt="QRIS {{ config('payments.qris.merchant_name', 'ASNet') }}"><p>Scan untuk melakukan pembayaran</p><small>Aktif sampai {{ optional($payment->qris_expires_at)->format('d/m/Y H:i') }}</small>
                @else
                    <h2>{{ $methodLabels[$payment->method] ?? 'Bukti Pembayaran' }}</h2><div class="payment-method-placeholder"><img src="{{ asset('images/icons/payment.svg') }}" alt=""><strong>{{ $payment->method === 'qris' && $qrisExpired ? 'QRIS Kedaluwarsa' : 'Pembayaran Manual' }}</strong><span>Periksa bukti pembayaran sebelum melakukan verifikasi.</span></div>
                @endif
            </section>
            <section class="payment-proof-panel">
                <h2>Pratinjau Bukti Transfer</h2>
                @if ($payment->proof_path)
                    <a href="{{ route('payments.proof', $payment) }}" target="_blank" class="proof-preview"><img src="{{ asset('images/icons/file.svg') }}" alt=""><span>Buka bukti pembayaran</span></a>
                @else
                    <div class="proof-preview proof-empty"><img src="{{ asset('images/icons/file.svg') }}" alt=""><span>Tidak ada bukti pembayaran</span></div>
                @endif
                <label>Status Verifikasi<select disabled><option>{{ $payment->status === 'pending' ? 'Menunggu' : ucfirst($payment->status) }}</option></select></label>
                <label>Catatan<textarea readonly placeholder="Tidak ada catatan">{{ $payment->notes }}</textarea></label>
                @if ($payment->status === 'pending')
                    <div class="payment-action-row"><form action="{{ route('payments.verify', $payment) }}" method="POST">@csrf<button type="submit" class="verify-payment-button">✓ Verifikasi Pembayaran</button></form><form action="{{ route('payments.reject', $payment) }}" method="POST">@csrf<button type="submit" class="reject-payment-button">× Tolak Pembayaran</button></form></div>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
