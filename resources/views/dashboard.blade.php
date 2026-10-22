<x-app-layout>
    @php
        /*
         * Defensive defaults keep the dashboard renderable while an old
         * cached route/controller is being replaced during local deployment.
         * The DashboardController still supplies the real values in normal use.
         */
        $stats = $stats ?? [];
        $stats += [
            'activeCustomers' => 0,
            'totalCustomers' => $stats['activeCustomers'] ?? 0,
            'unpaidInvoices' => 0,
            'overdueInvoices' => 0,
            'isolatedCustomers' => 0,
            'pendingPaymentAmount' => 0,
        ];
        $revenueByMonth = collect($revenueByMonth ?? []);
        if ($revenueByMonth->isEmpty()) {
            $revenueByMonth = collect(range(6, 0))->map(fn (int $monthsAgo): array => [
                'label' => now()->subMonths($monthsAgo)->translatedFormat('M'),
                'amount' => 0,
            ]);
        }
        $maxRevenue = $maxRevenue ?? max((float) $revenueByMonth->max('amount'), 1);
        $recentPayments = $recentPayments ?? collect();
        $priorityCustomers = $priorityCustomers ?? collect();
        $chartWidth = 1000;
        $chartHeight = 210;
        $chartPoints = $revenueByMonth->values()->map(function ($month, $index) use ($chartWidth, $chartHeight, $maxRevenue, $revenueByMonth) {
            $x = $revenueByMonth->count() > 1 ? ($index / ($revenueByMonth->count() - 1)) * $chartWidth : $chartWidth / 2;
            $y = $chartHeight - (($month['amount'] / max($maxRevenue, 1)) * 165) - 15;
            return number_format($x, 1, '.', '') . ',' . number_format($y, 1, '.', '');
        })->implode(' ');
        $areaPoints = '0,' . $chartHeight . ' ' . $chartPoints . ' ' . $chartWidth . ',' . $chartHeight;
        $currentRevenue = (float) $revenueByMonth->last()['amount'];
    @endphp

    <div class="dashboard-hero">
        <div>
            <h2>Selamat Datang, {{ Auth::user()->name ?? 'Pemilik ASNet' }}</h2>
            <p>Ringkasan operasional jaringan RT/RW Net Anda hari ini.</p>
        </div>
        <div class="dashboard-actions">
            <a href="{{ route('customers.create') }}" class="dashboard-action primary"><img src="{{ asset('images/icons/plus.svg') }}" alt=""> Pelanggan baru</a>
            <a href="{{ route('invoices.create') }}" class="dashboard-action"><img src="{{ asset('images/icons/plus.svg') }}" alt=""> Buat invoice</a>
        </div>
    </div>

    <section class="metric-grid" aria-label="Ringkasan operasional">
        <article class="metric-card metric-blue">
            <div class="metric-card-heading"><p>Pelanggan Aktif</p><div class="metric-icon"><img src="{{ asset('images/icons/customers.svg') }}" class="metric-icon-img" alt=""></div></div>
            <strong>{{ number_format($stats['activeCustomers']) }}</strong><small>Total: {{ number_format($stats['totalCustomers']) }}</small>
        </article>
        <article class="metric-card metric-green">
            <div class="metric-card-heading"><p>Pendapatan Bulan Ini</p><div class="metric-icon"><img src="{{ asset('images/icons/payment.svg') }}" class="metric-icon-img" alt=""></div></div>
            <strong>Rp {{ number_format($currentRevenue, 0, ',', '.') }}</strong><small>Pembayaran terverifikasi</small>
        </article>
        <article class="metric-card metric-orange">
            <div class="metric-card-heading"><p>Invoice Belum Dibayar</p><div class="metric-icon"><img src="{{ asset('images/icons/invoice.svg') }}" class="metric-icon-img" alt=""></div></div>
            <strong>Rp {{ number_format((float) $stats['pendingPaymentAmount'], 0, ',', '.') }}</strong><small>{{ number_format($stats['unpaidInvoices'] + $stats['overdueInvoices']) }} invoice</small>
        </article>
        <article class="metric-card metric-red">
            <div class="metric-card-heading"><p>Pelanggan Terisolir</p><div class="metric-icon"><img src="{{ asset('images/icons/isolation.svg') }}" class="metric-icon-img" alt=""></div></div>
            <strong>{{ number_format($stats['isolatedCustomers']) }}</strong><small>Aksi diperlukan</small>
        </article>
    </section>

    <div class="dashboard-content-grid">
        <section class="dashboard-panel revenue-panel">
            <div class="panel-heading"><div><h3>Pendapatan 7 Bulan Terakhir</h3></div></div>
            <div class="revenue-chart-reference" aria-label="Grafik pendapatan">
                <div class="chart-y-axis">
                    <span>Rp {{ number_format($maxRevenue, 0, ',', '.') }}</span><span>Rp {{ number_format($maxRevenue * .75, 0, ',', '.') }}</span><span>Rp {{ number_format($maxRevenue * .50, 0, ',', '.') }}</span><span>Rp {{ number_format($maxRevenue * .25, 0, ',', '.') }}</span><span>Rupiah</span>
                </div>
                <div class="chart-plot">
                    <svg viewBox="0 0 {{ $chartWidth }} {{ $chartHeight }}" preserveAspectRatio="xMidYMid meet" role="img" aria-label="Grafik pendapatan terverifikasi">
                        <defs><linearGradient id="revenueFill" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="#5c8df5" stop-opacity=".48"/><stop offset="1" stop-color="#dce8ff" stop-opacity=".18"/></linearGradient></defs>
                        @foreach ([15, 56, 97, 138, 179] as $lineY)<line x1="0" y1="{{ $lineY }}" x2="{{ $chartWidth }}" y2="{{ $lineY }}" stroke="#e4eaf2" stroke-width="1" vector-effect="non-scaling-stroke"/>@endforeach
                        <polygon points="{{ $areaPoints }}" fill="url(#revenueFill)"/>
                        <polyline points="{{ $chartPoints }}" fill="none" stroke="#2e67c7" stroke-width="4" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke"/>
                        @foreach ($revenueByMonth->values() as $index => $month)
                            @php $point = explode(',', explode(' ', $chartPoints)[$index]); @endphp
                            <circle cx="{{ $point[0] }}" cy="{{ $point[1] }}" r="6" fill="#fff" stroke="#2e67c7" stroke-width="3" vector-effect="non-scaling-stroke"/>
                        @endforeach
                    </svg>
                    <div class="chart-x-axis">@foreach ($revenueByMonth as $month)<span>{{ $month['label'] }}</span>@endforeach</div>
                </div>
            </div>
        </section>

        <section class="dashboard-panel activity-panel">
            <div class="panel-heading"><div><h3>Aktivitas Terbaru</h3></div><a href="{{ route('payments.index') }}">Lihat Semua</a></div>
            <div class="activity-list">
                @forelse ($recentPayments as $payment)
                    <div class="activity-item"><span class="activity-icon"><img src="{{ asset('images/icons/payment.svg') }}" alt=""></span><div><strong>Pembayaran Diterima</strong><span>{{ $payment->invoice->customer->name ?? '-' }} - Rp {{ number_format((float) $payment->amount, 0, ',', '.') }}</span><small>{{ optional($payment->verified_at)->diffForHumans() }}</small></div></div>
                @empty
                    <p class="empty-inline">Belum ada aktivitas pembayaran.</p>
                @endforelse
            </div>
        </section>
    </div>

    <section class="dashboard-panel priority-panel">
        <div class="panel-heading"><div><h3>Pelanggan Prioritas</h3></div></div>
        <div class="table-scroll"><table class="priority-table">
            <thead><tr><th>Nama Pelanggan</th><th>Status</th><th>Tagihan Tertunggak</th><th>Terakhir Online</th><th>Aksi</th></tr></thead>
            <tbody>
                @forelse ($priorityCustomers as $customer)
                    <tr><td><strong>{{ $customer->name }}</strong></td><td><span class="status-badge status-{{ $customer->status }}">{{ ucfirst($customer->status) }}</span></td><td>Rp {{ number_format((float) $customer->invoices->sum('total_amount'), 0, ',', '.') }}</td><td>{{ optional($customer->updated_at)->diffForHumans() ?? '-' }}</td><td><a href="{{ route('customers.show', $customer) }}">Detail</a></td></tr>
                @empty
                    <tr><td colspan="5" class="empty-table">Tidak ada pelanggan prioritas saat ini.</td></tr>
                @endforelse
            </tbody>
        </table></div>
    </section>
</x-app-layout>
