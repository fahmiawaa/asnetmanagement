<x-app-layout>
    @php
        $billingPayments = $customer->invoices
            ->flatMap(fn ($invoice) => $invoice->payments)
            ->sortByDesc('payment_date')
            ->values();
        $nextInvoice = $customer->invoices->first();
        $statusLabels = [
            'active' => 'Aktif',
            'inactive' => 'Nonaktif',
            'overdue' => 'Menunggak',
            'isolated' => 'Terisolir',
            'stopped' => 'Berhenti',
            'suspended' => 'Suspended',
            'unmapped' => 'Belum Dipetakan',
        ];
    @endphp

    <div class="customer-detail-page">
        <div class="customer-detail-heading">
            <div>
                <p class="detail-breadcrumb">Beranda <span>›</span> Pelanggan <span>›</span> Detail Pelanggan</p>
                <h1>Detail Pelanggan</h1>
            </div>
            <a href="{{ route('customers.edit', $customer) }}" class="reference-primary-action"><img src="{{ asset('images/icons/edit.svg') }}" alt=""> Edit Pelanggan</a>
        </div>

        <div class="customer-detail-top-grid">
            <section class="detail-reference-card customer-profile-card">
                <h2>Profil Pelanggan</h2>
                <div class="customer-profile-main">
                    <span class="customer-avatar-large">{{ strtoupper(substr($customer->name, 0, 1)) }}</span>
                    <div><h3>{{ $customer->name }}</h3><p>Kode Pelanggan: {{ $customer->customer_code }} <span class="status-pill status-pill-active">{{ $statusLabels[$customer->status] ?? $customer->status }}</span></p></div>
                </div>
                <div class="customer-contact-lines">
                    <span><img src="{{ asset('images/icons/mail.svg') }}" alt="">{{ $customer->user->email ?? '-' }}</span>
                    <span><img src="{{ asset('images/icons/phone.svg') }}" alt="">{{ $customer->whatsapp_number ?? '-' }}</span>
                    <span><img src="{{ asset('images/icons/location.svg') }}" alt="">{{ $customer->address ?? '-' }}</span>
                </div>
            </section>

            <section class="detail-reference-card package-summary-card">
                <h2>Ringkasan Paket</h2>
                <dl>
                    <div><dt>Nama Paket:</dt><dd>{{ $customer->internetPackage->name ?? '-' }}</dd></div>
                    <div><dt>Harga:</dt><dd>Rp {{ number_format((float) ($customer->internetPackage->price ?? 0), 0, ',', '.') }} / bulan</dd></div>
                    <div><dt>Kecepatan:</dt><dd>{{ $customer->internetPackage->download_speed ?? '-' }} DL / {{ $customer->internetPackage->upload_speed ?? '-' }} UL</dd></div>
                    <div><dt>Status:</dt><dd class="text-success">{{ $statusLabels[$customer->status] ?? $customer->status }}</dd></div>
                </dl>
                <div class="package-usage"><div><span>Pemakaian Bulan Ini:</span><b>Belum tersedia</b></div><div class="package-usage-bar"><span style="width:0%"></span></div></div>
            </section>
        </div>

        <section class="detail-reference-card billing-summary-card">
            <h2>Ikhtisar Tagihan</h2>
            <div class="billing-summary-grid">
                <div><span>Total Tagihan Belum Bayar:</span><strong>Rp {{ number_format((float) $totalOutstanding, 0, ',', '.') }}</strong></div>
                <div><span>Tagihan Bulan Ini:</span><strong>Rp {{ number_format((float) ($nextInvoice->total_amount ?? 0), 0, ',', '.') }}</strong></div>
                <div><span>Saldo Deposit:</span><strong>Rp 0</strong></div>
                <div><span>Jatuh Tempo Berikutnya:</span><strong>{{ optional($nextInvoice?->due_date)->translatedFormat('d F Y') ?? '-' }}</strong></div>
            </div>
        </section>

        <div class="customer-detail-middle-grid">
            <section class="detail-reference-card compact-table-card">
                <div class="detail-card-heading"><h2>Faktur Terbaru</h2><a href="{{ route('invoices.index', ['q' => $customer->customer_code]) }}">Lihat Semua Faktur</a></div>
                <table class="detail-reference-table"><thead><tr><th>No. Faktur</th><th>Tanggal</th><th>Jatuh Tempo</th><th>Jumlah</th><th>Status</th></tr></thead><tbody>
                    @forelse ($customer->invoices->take(3) as $invoice)
                        <tr><td>{{ $invoice->invoice_number }}</td><td>{{ optional($invoice->period_start)->format('d M Y') }}</td><td>{{ optional($invoice->due_date)->format('d M Y') }}</td><td>Rp {{ number_format((float) $invoice->total_amount, 0, ',', '.') }}</td><td><span class="status-badge status-{{ $invoice->status }}">{{ ucfirst($invoice->status) }}</span></td></tr>
                    @empty
                        <tr><td colspan="5" class="empty-table">Belum ada faktur.</td></tr>
                    @endforelse
                </tbody></table>
            </section>
            <section class="detail-reference-card compact-table-card">
                <div class="detail-card-heading"><h2>Riwayat Pembayaran</h2><a href="{{ route('payments.index', ['q' => $customer->customer_code]) }}">Lihat Semua Pembayaran</a></div>
                <table class="detail-reference-table"><thead><tr><th>ID Pembayaran</th><th>Tanggal</th><th>Metode</th><th>Jumlah</th><th>Status</th></tr></thead><tbody>
                    @forelse ($billingPayments->take(3) as $payment)
                        <tr><td>PAY-{{ str_pad($payment->id, 6, '0', STR_PAD_LEFT) }}</td><td>{{ optional($payment->payment_date)->format('d M Y') }}</td><td>{{ strtoupper($payment->method) }}</td><td>Rp {{ number_format((float) $payment->amount, 0, ',', '.') }}</td><td><span class="status-badge status-{{ $payment->status }}">{{ ucfirst($payment->status) }}</span></td></tr>
                    @empty
                        <tr><td colspan="5" class="empty-table">Belum ada pembayaran.</td></tr>
                    @endforelse
                </tbody></table>
            </section>
        </div>

        <div class="customer-detail-bottom-grid">
            <section class="detail-reference-card location-detail-card">
                <h2>Lokasi</h2>
                @if ($customer->latitude && $customer->longitude)
                    <div id="customer-detail-map" class="customer-detail-map"></div>
                @else
                    <div class="customer-detail-map map-detail-empty">Lokasi pelanggan belum ditentukan.</div>
                @endif
                <p>{{ $customer->address ?? '-' }}</p>
            </section>
            <section class="detail-reference-card pppoe-detail-card">
                <h2>Status Akun PPPoE</h2>
                @if ($customer->pppoeAccount)
                    <div class="pppoe-detail-grid">
                        <div><span>Username PPPoE</span><b>{{ $customer->pppoeAccount->username }}</b></div>
                        <div><span>Status Koneksi</span><b class="text-success">{{ $customer->pppoeAccount->disabled ? 'Disabled' : 'Terhubung' }}</b></div>
                        <div><span>Profile</span><b>{{ $customer->pppoeAccount->profile ?? '-' }}</b></div>
                        <div><span>Router</span><b>{{ $customer->pppoeAccount->mikrotikDevice->name ?? '-' }}</b></div>
                        <div><span>Service</span><b>{{ $customer->pppoeAccount->service ?? '-' }}</b></div>
                        <div><span>Provision Status</span><b>{{ ucfirst($customer->pppoeAccount->provision_status ?? '-') }}</b></div>
                    </div>
                @else
                    <p class="empty-inline">Pelanggan belum memiliki akun PPPoE.</p>
                @endif
            </section>
        </div>

        <section class="detail-reference-card ont-detail-card">
            <div class="detail-card-heading"><h2>Perangkat ONT</h2>@if ($customer->ontDevice)<a href="{{ route('ont-devices.show', $customer->ontDevice) }}">Lihat Detail ONT</a>@endif</div>
            @if ($customer->ontDevice)
                <div class="pppoe-detail-grid">
                    <div><span>Serial</span><b>{{ $customer->ontDevice->serial_number ?? '-' }}</b></div>
                    <div><span>Perangkat</span><b>{{ trim(($customer->ontDevice->manufacturer ?? '').' '.($customer->ontDevice->product_class ?? '')) ?: '-' }}</b></div>
                    <div><span>Status ACS</span><b><span class="status-badge ont-status-{{ $customer->ontDevice->connection_status }}">{{ ucfirst($customer->ontDevice->connection_status) }}</span></b></div>
                    <div><span>RX Power</span><b>{{ $customer->ontDevice->rx_power_dbm !== null ? number_format((float) $customer->ontDevice->rx_power_dbm, 2).' dBm' : 'Tidak valid' }}</b></div>
                </div>
            @else
                <p class="empty-inline">Pelanggan belum dipetakan ke ONT.</p>
            @endif
        </section>
    </div>

    @if ($customer->latitude && $customer->longitude)
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const element = document.getElementById('customer-detail-map');
                if (!element || !window.L) return;
                const map = L.map(element, { zoomControl: false, attributionControl: true }).setView([{{ $customer->latitude }}, {{ $customer->longitude }}], 16);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap contributors' }).addTo(map);
                L.marker([{{ $customer->latitude }}, {{ $customer->longitude }}]).addTo(map);
            });
        </script>
    @endif
</x-app-layout>
