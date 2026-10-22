<x-app-layout>
    <div class="customer-list-page ont-list-page">
        <div class="reference-page-heading">
            <div>
                <h1>Gangguan Optik &amp; ONT</h1>
                <p class="ont-muted">Daftar perangkat dengan status offline atau redaman di luar ambang normal.</p>
            </div>
        </div>

        @if (! $alertsEnabled)
            <div class="ont-banner ont-banner-warning" role="status">
                Notifikasi alert ONT sedang dinonaktifkan melalui konfigurasi <code>GENIEACS_ALERTS_ENABLED</code>.
            </div>
        @endif

        <div class="ont-stats-grid">
            <div class="ont-stat-card ont-stat-offline"><span>Offline</span><strong>{{ $stats['offline'] }}</strong></div>
            <div class="ont-stat-card ont-stat-warning"><span>Missing</span><strong>{{ $stats['missing'] }}</strong></div>
            <div class="ont-stat-card ont-stat-offline"><span>Redaman Kritis</span><strong>{{ $stats['critical'] }}</strong></div>
            <div class="ont-stat-card ont-stat-offline"><span>Redaman Overload</span><strong>{{ $stats['overload'] }}</strong></div>
        </div>

        <div class="reference-content-card customer-list-card">
            <form method="GET" action="{{ route('optical-alerts.index') }}" class="ont-list-filters">
                <select name="type" aria-label="Filter jenis gangguan">
                    <option value="all" @selected($type === 'all')>Semua Gangguan</option>
                    <option value="offline" @selected($type === 'offline')>Offline / Missing</option>
                    <option value="optical" @selected($type === 'optical')>Redaman Bermasalah</option>
                </select>
                <button type="submit" class="customer-filter-button">Filter</button>
            </form>

            <div class="reference-table-panel customer-table-panel">
                <div class="overflow-x-auto">
                    <table class="customer-reference-table ont-table">
                        <thead>
                            <tr>
                                <th>Perangkat</th>
                                <th>Pelanggan</th>
                                <th>Node</th>
                                <th>Status</th>
                                <th>RX Power</th>
                                <th>Last Inform</th>
                                <th>Tindakan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($alerts as $ont)
                                <tr>
                                    <td>
                                        <strong>{{ $ont->serial_number ?? $ont->genieacs_device_id }}</strong>
                                        <small class="ont-muted">{{ $ont->pon_mac ?? $ont->genieacs_device_id }}</small>
                                    </td>
                                    <td>
                                        @if ($ont->customer)
                                            <strong>{{ $ont->customer->name }}</strong>
                                            <small class="ont-muted">{{ $ont->customer->customer_code }}</small>
                                        @else
                                            <span class="status-badge status-unmapped">Belum dipetakan</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($ont->networkNode)
                                            {{ $ont->networkNode->name }}
                                            <small class="ont-muted">{{ strtoupper($ont->networkNode->type) }}</small>
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td><span class="status-badge ont-status-{{ $ont->connection_status }}">{{ $statusLabels[$ont->connection_status] ?? $ont->connection_status }}</span></td>
                                    <td>
                                        @if ($ont->rx_power_dbm !== null)
                                            <span class="ont-rx ont-rx-{{ $ont->rx_power_quality }}">{{ number_format((float) $ont->rx_power_dbm, 2) }} dBm</span>
                                            <small class="ont-muted">{{ $qualityLabels[$ont->rx_power_quality] ?? $ont->rx_power_quality }}</small>
                                        @else
                                            <span class="ont-rx ont-rx-unknown">Tidak valid</span>
                                        @endif
                                    </td>
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
                                <tr><td colspan="7" class="empty-table">Tidak ada gangguan tercatat. Semua ONT dalam kondisi normal.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="customer-table-footer">
                    <span>Menampilkan {{ $alerts->firstItem() ?? 0 }}-{{ $alerts->lastItem() ?? 0 }} dari {{ $alerts->total() }} data</span>
                    <div>{{ $alerts->onEachSide(1)->links() }}</div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
