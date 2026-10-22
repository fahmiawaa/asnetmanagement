<x-app-layout>
    @php
        $package = $customer->internetPackage;
        $statusLabels = [
            'active' => 'Aktif',
            'overdue' => 'Menunggak',
            'isolated' => 'Terisolir',
            'inactive' => 'Nonaktif',
        ];
        $statusLabel = $statusLabels[$customer->status] ?? ucfirst($customer->status);
        $qrisImagePath = config('payments.qris.image_path');
        $qrisAvailable = config('payments.qris.enabled')
            && is_string($qrisImagePath)
            && is_file(public_path($qrisImagePath));
        $currentInvoice = $currentInvoice ?? null;
        $mobileGreeting = \Illuminate\Support\Str::of($customer->name)->before(' ')->value();
    @endphp

    <div class="customer-portal-mobile">
        <header class="customer-portal-header">
            <div class="customer-portal-brand">
                <img src="{{ asset('images/icons/asnet-mark.svg') }}" alt="ASNet">
                <span>ASNet</span>
            </div>
            <div class="customer-portal-header-actions">
                <button type="button" class="customer-portal-globe" aria-label="Bahasa">◎</button>
                <span class="customer-portal-greeting">Halo, {{ $mobileGreeting ?: $customer->name }}</span>
                <a href="{{ route('profile.edit') }}" class="customer-portal-avatar" aria-label="Buka profil">
                    {{ strtoupper(substr($customer->name, 0, 1)) }}
                </a>
            </div>
        </header>

        <div class="customer-portal-blue-strip" aria-hidden="true"></div>

        <main class="customer-portal-content">
            <section class="customer-service-card">
                <div class="customer-service-heading">
                    <span>Status Layanan</span>
                    <span class="customer-status-pill customer-status-{{ $customer->status }}">
                        <i></i>{{ $statusLabel }}
                    </span>
                </div>
                <h1>{{ $package->name ?? 'Paket internet belum ditentukan' }}</h1>
                <p class="customer-service-caption">Layanan internet Anda</p>
                <div class="customer-service-meta">
                    <span><img src="{{ asset('images/icons/payment.svg') }}" alt="">Tagihan tanggal {{ $customer->due_day }}</span>
                    <span>{{ $package?->download_speed ? $package->download_speed . ' Mbps' : 'Hubungi operator' }}</span>
                </div>
            </section>

            <section class="customer-portal-card customer-bill-card">
                <div class="customer-portal-card-heading">
                    <h2><img src="{{ asset('images/icons/invoice.svg') }}" alt="">Tagihan Bulan Ini</h2>
                    @if ($currentInvoice)
                        <span class="customer-invoice-status">{{ $currentInvoice->status === 'overdue' ? 'Terlambat' : 'Belum bayar' }}</span>
                    @endif
                </div>
                <strong class="customer-bill-amount">Rp {{ number_format((float) ($currentInvoice?->total_amount ?? $outstandingAmount), 0, ',', '.') }}</strong>
                <p>
                    @if ($currentInvoice?->due_date)
                        Jatuh tempo: {{ $currentInvoice->due_date->translatedFormat('d M Y') }}
                    @else
                        Tidak ada tagihan yang harus dibayar
                    @endif
                </p>
                @if ($currentInvoice)
                    <a href="{{ route('customer.payments.create', ['invoice_id' => $currentInvoice->id]) }}" class="customer-portal-primary-button">
                        <img src="{{ asset('images/icons/payment.svg') }}" alt=""> Bayar Sekarang
                    </a>
                @else
                    <a href="{{ route('customer.invoices.index') }}" class="customer-portal-secondary-button">Lihat Riwayat Invoice</a>
                @endif
            </section>

            <section class="customer-portal-card customer-qris-card">
                <div class="customer-portal-card-heading">
                    <h2><img src="{{ asset('images/icons/qr.svg') }}" alt="">Pembayaran Cepat QRIS</h2>
                </div>
                @if ($qrisAvailable)
                    <img class="customer-qris-image" src="{{ asset($qrisImagePath) }}" alt="QRIS ASNet">
                    <p>Scan QRIS melalui aplikasi pembayaran Anda</p>
                    <small>QRIS aktif {{ config('payments.qris.expiration_hours', 24) }} jam sejak pengajuan dibuat.</small>
                @else
                    <div class="customer-qris-unavailable">
                        <img src="{{ asset('images/icons/qr.svg') }}" alt="">
                        <p>QRIS belum tersedia</p>
                        <small>Gunakan tombol Bayar Sekarang untuk mengajukan pembayaran.</small>
                    </div>
                @endif
            </section>

            <section class="customer-payment-history">
                <div class="customer-portal-section-heading">
                    <h2><img src="{{ asset('images/icons/clock.svg') }}" alt="">Riwayat Pembayaran</h2>
                    <a href="{{ route('customer.payments.index') }}">Lihat semua</a>
                </div>
                @forelse ($payments as $payment)
                    <a class="customer-payment-history-item" href="{{ route('customer.payments.show', $payment) }}">
                        <span>{{ optional($payment->payment_date)->translatedFormat('d M Y') ?? '-' }} - {{ $payment->method === 'qris' ? 'QRIS' : ($payment->method === 'e_wallet' ? 'E-Wallet' : 'Transfer Bank') }}</span>
                        <strong>Rp {{ number_format((float) $payment->amount, 0, ',', '.') }}</strong>
                        <i class="payment-status-dot payment-status-{{ $payment->status }}"></i>
                    </a>
                @empty
                    <div class="customer-payment-empty">Belum ada riwayat pembayaran.</div>
                @endforelse
            </section>
        </main>

    </div>

    {{-- Desktop pelanggan memakai shell dan pola visual yang sama dengan dashboard admin. --}}
    <div class="customer-dashboard-desktop">
        <div class="dashboard-hero">
            <div>
                <p class="dashboard-kicker">ASNET CUSTOMER PORTAL</p>
                <h2>Selamat datang, {{ $customer->name }}</h2>
                <p>Ringkasan layanan, tagihan, dan pembayaran internet Anda.</p>
            </div>
            <div class="dashboard-actions">
                <a href="{{ route('customer.invoices.index') }}" class="dashboard-action primary">
                    <img src="{{ asset('images/icons/invoice.svg') }}" alt=""> Lihat invoice
                </a>
                <a href="{{ route('customer.payments.create') }}" class="dashboard-action">
                    <img src="{{ asset('images/icons/payment.svg') }}" alt=""> Bayar tagihan
                </a>
            </div>
        </div>

        <section class="metric-grid customer-dashboard-metrics" aria-label="Ringkasan akun pelanggan">
            <article class="metric-card metric-blue">
                <div class="metric-card-heading">
                    <p>Status layanan</p>
                    <div class="metric-icon"><img src="{{ asset('images/icons/customers.svg') }}" class="metric-icon-img" alt=""></div>
                </div>
                <strong>{{ $statusLabel }}</strong>
                <small>{{ $customer->customer_code }}</small>
            </article>
            <article class="metric-card metric-indigo">
                <div class="metric-card-heading">
                    <p>Paket internet</p>
                    <div class="metric-icon"><img src="{{ asset('images/icons/package.svg') }}" class="metric-icon-img" alt=""></div>
                </div>
                <strong>{{ $package?->name ?? 'Belum diatur' }}</strong>
                <small>{{ $package?->download_speed ? $package->download_speed . ' Mbps' : 'Hubungi operator' }}</small>
            </article>
            <article class="metric-card metric-orange">
                <div class="metric-card-heading">
                    <p>Tagihan berjalan</p>
                    <div class="metric-icon"><img src="{{ asset('images/icons/invoice.svg') }}" class="metric-icon-img" alt=""></div>
                </div>
                <strong>Rp {{ number_format((float) $outstandingAmount, 0, ',', '.') }}</strong>
                <small>{{ $currentInvoice ? 'Menunggu pembayaran' : 'Tidak ada tagihan' }}</small>
            </article>
            <article class="metric-card metric-green">
                <div class="metric-card-heading">
                    <p>Koneksi PPPoE</p>
                    <div class="metric-icon"><img src="{{ asset('images/icons/pppoe.svg') }}" class="metric-icon-img" alt=""></div>
                </div>
                <strong>{{ $customer->pppoeAccount?->disabled ? 'Nonaktif' : ($customer->pppoeAccount ? 'Terhubung' : 'Belum ada') }}</strong>
                <small>{{ $customer->pppoeAccount?->username ?? 'Akun belum dibuat' }}</small>
            </article>
        </section>

        <div class="dashboard-content-grid customer-dashboard-content">
            <section class="dashboard-panel customer-dashboard-invoices">
                <div class="panel-heading">
                    <div>
                        <h3>Invoice Terbaru</h3>
                        <p>Daftar tagihan akun Anda.</p>
                    </div>
                    <a href="{{ route('customer.invoices.index') }}">Lihat semua</a>
                </div>
                <div class="table-scroll">
                    <table class="priority-table">
                        <thead>
                            <tr>
                                <th>Nomor Invoice</th>
                                <th>Jatuh Tempo</th>
                                <th>Jumlah</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($invoices as $invoice)
                                <tr>
                                    <td><a href="{{ route('customer.invoices.show', $invoice) }}">{{ $invoice->invoice_number }}</a></td>
                                    <td>{{ optional($invoice->due_date)->format('d M Y') ?? '-' }}</td>
                                    <td>Rp {{ number_format((float) $invoice->total_amount, 0, ',', '.') }}</td>
                                    <td><span class="status-badge status-{{ $invoice->status }}">{{ ucfirst($invoice->status) }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="empty-table">Belum ada invoice.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="dashboard-panel customer-dashboard-account">
                <div class="panel-heading">
                    <div>
                        <h3>Ringkasan akun</h3>
                        <p>Informasi layanan pelanggan.</p>
                    </div>
                </div>
                <dl class="customer-dashboard-details">
                    <div><dt>Nama pelanggan</dt><dd>{{ $customer->name }}</dd></div>
                    <div><dt>WhatsApp</dt><dd>{{ $customer->whatsapp_number ?: '-' }}</dd></div>
                    <div><dt>Jatuh tempo</dt><dd>Tanggal {{ $customer->due_day }}</dd></div>
                    <div><dt>Alamat</dt><dd>{{ $customer->address ?: '-' }}</dd></div>
                </dl>
                <a href="{{ route('profile.edit') }}" class="customer-dashboard-profile-link">Buka pengaturan profil</a>
            </section>
        </div>

        <section class="dashboard-panel priority-panel customer-dashboard-payments">
            <div class="panel-heading">
                <div>
                    <h3>Riwayat Pembayaran</h3>
                    <p>Pembayaran terakhir yang tercatat pada akun Anda.</p>
                </div>
                <a href="{{ route('customer.payments.index') }}">Lihat semua</a>
            </div>
            <div class="table-scroll">
                <table class="priority-table">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Invoice</th>
                            <th>Metode</th>
                            <th>Jumlah</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($payments as $payment)
                            <tr>
                                <td>{{ optional($payment->payment_date)->format('d M Y') ?? '-' }}</td>
                                <td><a href="{{ route('customer.payments.show', $payment) }}">{{ $payment->invoice->invoice_number ?? '-' }}</a></td>
                                <td>{{ $payment->method === 'qris' ? 'QRIS' : ($payment->method === 'e_wallet' ? 'E-Wallet' : 'Transfer Bank') }}</td>
                                <td>Rp {{ number_format((float) $payment->amount, 0, ',', '.') }}</td>
                                <td><span class="status-badge status-{{ $payment->status }}">{{ ucfirst($payment->status) }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="empty-table">Belum ada riwayat pembayaran.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-app-layout>