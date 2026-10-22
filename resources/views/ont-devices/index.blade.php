<x-app-layout>
    @php
        $sortUrl = function (string $key) use ($sort, $direction): string {
            $nextDirection = $sort === $key && $direction === 'asc' ? 'desc' : 'asc';

            return route('ont-devices.index', array_merge(
                request()->except(['page', 'sort', 'direction']),
                ['sort' => $key, 'direction' => $nextDirection]
            ));
        };
        $arrow = fn (string $key): string => $sort === $key ? ($direction === 'asc' ? '▲' : '▼') : '';
    @endphp

    <div class="customer-list-page ont-list-page">
        <div class="reference-page-heading">
            <div>
                <h1>ONT Devices</h1>
            </div>
        </div>

        @if ($syncStale)
            <div class="ont-banner ont-banner-warning" role="status">
                @if ($servers->isEmpty())
                    Belum ada server GenieACS aktif. Data ONT di bawah adalah snapshot lokal terakhir.
                @else
                    Sinkronisasi GenieACS sudah lebih dari {{ config('genieacs.sync_max_age_minutes') }} menit atau belum pernah berjalan. Status ONT di bawah mungkin tidak terkini.
                @endif
            </div>
        @endif

        <div class="ont-stats-grid">
            <div class="ont-stat-card"><span>Total ONT</span><strong>{{ $stats['total'] }}</strong></div>
            <div class="ont-stat-card ont-stat-online"><span>Online</span><strong>{{ $stats['online'] }}</strong></div>
            <div class="ont-stat-card ont-stat-warning"><span>Warning</span><strong>{{ $stats['warning'] }}</strong></div>
            <div class="ont-stat-card ont-stat-offline"><span>Offline</span><strong>{{ $stats['offline'] }}</strong></div>
            <div class="ont-stat-card ont-stat-offline"><span>RX Kritis</span><strong>{{ $stats['critical'] }}</strong></div>
            <div class="ont-stat-card"><span>Belum Dipetakan</span><strong>{{ $stats['unmapped'] }}</strong><small>{{ $stats['mapped_percent'] }}% sudah dipetakan</small></div>
        </div>

        <div class="reference-content-card customer-list-card">
            <form method="GET" action="{{ route('ont-devices.index') }}" class="ont-list-filters">
                <label class="customer-search-field">
                    <img src="{{ asset('images/icons/search.svg') }}" alt="">
                    <input type="search" name="q" value="{{ $search }}" placeholder="Cari pelanggan, serial, MAC, IP, PPPoE..." aria-label="Cari ONT" maxlength="100">
                </label>
                <select name="status" aria-label="Filter status">
                    <option value="">Semua Status</option>
                    @foreach ($statusLabels as $value => $label)
                        <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="quality" aria-label="Filter kualitas RX">
                    <option value="">Semua Kualitas RX</option>
                    @foreach ($qualityLabels as $value => $label)
                        <option value="{{ $value }}" @selected($quality === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="pon_mode" aria-label="Filter PON mode">
                    <option value="">Semua PON</option>
                    <option value="gpon" @selected($ponMode === 'gpon')>GPON</option>
                    <option value="epon" @selected($ponMode === 'epon')>EPON</option>
                    <option value="unknown" @selected($ponMode === 'unknown')>Unknown</option>
                </select>
                <select name="mapping" aria-label="Filter mapping">
                    <option value="">Semua Mapping</option>
                    <option value="mapped" @selected($mapping === 'mapped')>Sudah dipetakan</option>
                    <option value="unmapped" @selected($mapping === 'unmapped')>Belum dipetakan</option>
                </select>
                <input type="hidden" name="sort" value="{{ $sort }}">
                <input type="hidden" name="direction" value="{{ $direction }}">
                <button type="submit" class="customer-filter-button">Filter</button>
            </form>

            @error('sort')<p class="ont-form-error">{{ $message }}</p>@enderror
            @error('direction')<p class="ont-form-error">{{ $message }}</p>@enderror

            <div class="reference-table-panel customer-table-panel">
                <div class="overflow-x-auto">
                    <table class="customer-reference-table ont-table">
                        <thead>
                            <tr>
                                <th><a href="{{ $sortUrl('serial') }}">Serial {{ $arrow('serial') }}</a></th>
                                <th><a href="{{ $sortUrl('customer') }}">Pelanggan {{ $arrow('customer') }}</a></th>
                                <th><a href="{{ $sortUrl('manufacturer') }}">Vendor {{ $arrow('manufacturer') }}</a></th>
                                <th><a href="{{ $sortUrl('product_class') }}">Model {{ $arrow('product_class') }}</a></th>
                                <th><a href="{{ $sortUrl('pon_mode') }}">PON {{ $arrow('pon_mode') }}</a></th>
                                <th><a href="{{ $sortUrl('rx_power') }}">RX Power {{ $arrow('rx_power') }}</a></th>
                                <th><a href="{{ $sortUrl('status') }}">Status {{ $arrow('status') }}</a></th>
                                <th><a href="{{ $sortUrl('last_inform') }}">Last Inform {{ $arrow('last_inform') }}</a></th>
                                <th>Tindakan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($ontDevices as $ont)
                                <tr>
                                    <td>
                                        <strong>{{ $ont->serial_number ?? '-' }}</strong>
                                        <small class="ont-muted">{{ $ont->pon_mac ?? $ont->genieacs_device_id }}</small>
                                    </td>
                                    <td>
                                        @if ($ont->customer)
                                            <strong>{{ $ont->customer->name }}</strong>
                                            <small class="ont-muted">{{ $ont->customer->customer_code }}@if ($ont->customer->pppoeAccount) · {{ $ont->customer->pppoeAccount->username }}@endif</small>
                                        @else
                                            <span class="status-badge status-unmapped">Belum dipetakan</span>
                                        @endif
                                    </td>
                                    <td>{{ $ont->manufacturer ?? '-' }}</td>
                                    <td>{{ $ont->product_class ?? '-' }}</td>
                                    <td>{{ strtoupper($ont->pon_mode) }}</td>
                                    <td>
                                        @if ($ont->rx_power_dbm !== null)
                                            <span class="ont-rx ont-rx-{{ $ont->rx_power_quality }}">{{ number_format((float) $ont->rx_power_dbm, 2) }} dBm</span>
                                            <small class="ont-muted">{{ $qualityLabels[$ont->rx_power_quality] ?? $ont->rx_power_quality }}</small>
                                        @else
                                            <span class="ont-rx ont-rx-unknown">Tidak valid</span>
                                        @endif
                                    </td>
                                    <td><span class="status-badge ont-status-{{ $ont->connection_status }}">{{ $statusLabels[$ont->connection_status] ?? $ont->connection_status }}</span></td>
                                    <td>
                                        @if ($ont->last_inform_at)
                                            <span title="{{ $ont->last_inform_at->format('d M Y H:i:s') }}">{{ $ont->last_inform_at->diffForHumans() }}</span>
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>
                                        <div class="customer-table-actions">
                                            <a href="{{ route('ont-devices.show', $ont) }}" class="table-view-button"><img src="{{ asset('images/icons/eye.svg') }}" alt=""> Detail</a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="9" class="empty-table">Tidak ada ONT yang cocok dengan filter.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="customer-table-footer">
                    <span>Menampilkan {{ $ontDevices->firstItem() ?? 0 }}-{{ $ontDevices->lastItem() ?? 0 }} dari {{ $ontDevices->total() }} data</span>
                    <div>{{ $ontDevices->onEachSide(1)->links() }}</div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
