<x-app-layout>
    @php
        $methodLabels = [
            'manual' => 'Manual',
            'pppoe_mac' => 'PPPoE MAC',
            'pppoe_ip' => 'PPPoE IP',
            'pppoe_mac_ip' => 'PPPoE MAC + IP',
        ];
        $duration = function (?int $seconds): string {
            if ($seconds === null) {
                return '-';
            }

            return \Carbon\CarbonInterval::seconds($seconds)->cascade()->forHumans(['parts' => 2, 'short' => true]);
        };
        $dash = fn ($value) => filled($value) ? $value : '-';
    @endphp

    <div class="customer-detail-page ont-detail-page">
        <div class="customer-detail-heading">
            <div>
                <p class="detail-breadcrumb">Beranda <span>›</span> Network <span>›</span> ONT Devices</p>
                <h1>{{ $ontDevice->serial_number ?? $ontDevice->genieacs_device_id }}</h1>
            </div>
            <a href="{{ route('ont-devices.index') }}" class="reference-primary-action">Kembali ke Daftar</a>
        </div>

        <div class="ont-detail-grid">
            <section class="detail-reference-card">
                <h2>Identitas Perangkat</h2>
                <dl class="ont-dl">
                    <div><dt>Device ID</dt><dd>{{ $ontDevice->genieacs_device_id }}</dd></div>
                    <div><dt>Serial Number</dt><dd>{{ $dash($ontDevice->serial_number) }}</dd></div>
                    <div><dt>PON MAC</dt><dd>{{ $dash($ontDevice->pon_mac) }}</dd></div>
                    <div><dt>Vendor</dt><dd>{{ $dash($ontDevice->manufacturer) }}</dd></div>
                    <div><dt>OUI</dt><dd>{{ $dash($ontDevice->oui) }}</dd></div>
                    <div><dt>Model</dt><dd>{{ $dash($ontDevice->product_class) }}</dd></div>
                    <div><dt>Firmware</dt><dd>{{ $dash($ontDevice->software_version) }}</dd></div>
                    <div><dt>PON Mode</dt><dd>{{ strtoupper($ontDevice->pon_mode) }}</dd></div>
                    <div><dt>Server ACS</dt><dd>{{ $ontDevice->genieacsServer->name ?? '-' }}</dd></div>
                </dl>
            </section>

            <section class="detail-reference-card">
                <h2>Status ACS</h2>
                <dl class="ont-dl">
                    <div>
                        <dt>Status Saat Ini</dt>
                        <dd><span class="status-badge ont-status-{{ $liveStatus }}">{{ $statusLabels[$liveStatus] ?? $liveStatus }}</span></dd>
                    </div>
                    @if ($liveStatus !== $ontDevice->connection_status)
                        <div>
                            <dt>Snapshot Sinkron</dt>
                            <dd><span class="status-badge ont-status-{{ $ontDevice->connection_status }}">{{ $statusLabels[$ontDevice->connection_status] ?? $ontDevice->connection_status }}</span></dd>
                        </div>
                    @endif
                    <div>
                        <dt>Last Inform</dt>
                        <dd>
                            @if ($ontDevice->last_inform_at)
                                {{ $ontDevice->last_inform_at->format('d M Y H:i:s') }}
                                <small class="ont-muted">{{ $ontDevice->last_inform_at->diffForHumans() }}</small>
                            @else
                                -
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt>Interval Inform</dt>
                        <dd>
                            {{ $ontDevice->inform_interval_seconds ? $ontDevice->inform_interval_seconds.' detik' : 'Tidak dilaporkan (fallback '.config('genieacs.fallback_inform_interval_seconds').' detik)' }}
                        </dd>
                    </div>
                    <div><dt>Batas Online / Warning</dt><dd>≤ {{ $onlineWithinSeconds }} dtk / ≤ {{ $warningWithinSeconds }} dtk</dd></div>
                    <div><dt>IP TR-069</dt><dd>{{ $dash($ontDevice->ip_address) }}</dd></div>
                    <div><dt>IP PPPoE</dt><dd>{{ $dash($ontDevice->pppoe_ip) }}</dd></div>
                    <div><dt>MAC PPPoE</dt><dd>{{ $dash($ontDevice->pppoe_mac) }}</dd></div>
                    <div><dt>Sinkron Terakhir</dt><dd>{{ $ontDevice->last_synced_at?->format('d M Y H:i:s') ?? '-' }}</dd></div>
                </dl>
            </section>

            <section class="detail-reference-card">
                <h2>Redaman Optik &amp; Perangkat</h2>
                <dl class="ont-dl">
                    <div>
                        <dt>RX Power</dt>
                        <dd>
                            @if ($ontDevice->rx_power_dbm !== null)
                                <span class="ont-rx ont-rx-{{ $ontDevice->rx_power_quality }}">{{ number_format((float) $ontDevice->rx_power_dbm, 2) }} dBm</span>
                                <small class="ont-muted">{{ $qualityLabels[$ontDevice->rx_power_quality] ?? $ontDevice->rx_power_quality }}</small>
                            @else
                                <span class="ont-rx ont-rx-unknown">Tidak valid / tidak tersedia</span>
                            @endif
                        </dd>
                    </div>
                    <div><dt>Nilai Mentah</dt><dd>{{ $dash($ontDevice->rx_power_raw) }}</dd></div>
                    <div><dt>Sumber</dt><dd>{{ $dash($ontDevice->rx_power_source) }}</dd></div>
                    <div><dt>Diambil Pada</dt><dd>{{ $ontDevice->rx_power_collected_at?->format('d M Y H:i:s') ?? '-' }}</dd></div>
                    <div>
                        <dt>Ambang RX ({{ strtoupper($ontDevice->pon_mode) }})</dt>
                        <dd>
                            Baik {{ $rxThresholds['overload_above'] }} s.d. {{ $rxThresholds['good_min'] }} dBm ·
                            Warning ≥ {{ $rxThresholds['warning_min'] }} dBm ·
                            Kritis &lt; {{ $rxThresholds['warning_min'] }} dBm
                        </dd>
                    </div>
                    <div>
                        <dt>Temperatur</dt>
                        <dd>
                            {{ $ontDevice->temperature_celsius !== null ? number_format((float) $ontDevice->temperature_celsius, 1).' °C' : '-' }}
                            @if ($ontDevice->temperature_raw !== null && $ontDevice->temperature_celsius === null)
                                <small class="ont-muted">nilai mentah tidak valid: {{ $ontDevice->temperature_raw }}</small>
                            @endif
                        </dd>
                    </div>
                    <div><dt>Uptime Perangkat</dt><dd>{{ $duration($ontDevice->device_uptime_seconds) }}</dd></div>
                    <div><dt>Uptime PPPoE</dt><dd>{{ $duration($ontDevice->pppoe_uptime_seconds) }}</dd></div>
                    <div><dt>Perangkat WiFi Aktif</dt><dd>{{ $ontDevice->active_wifi_devices ?? '-' }}</dd></div>
                </dl>
            </section>

            <section class="detail-reference-card">
                <h2>Mapping Pelanggan</h2>

                @error('customer_id')<p class="ont-form-error">{{ $message }}</p>@enderror

                @if ($ontDevice->customer)
                    <dl class="ont-dl">
                        <div>
                            <dt>Pelanggan</dt>
                            <dd><a href="{{ route('customers.show', $ontDevice->customer) }}">{{ $ontDevice->customer->name }}</a> <small class="ont-muted">{{ $ontDevice->customer->customer_code }}</small></dd>
                        </div>
                        <div><dt>Username PPPoE</dt><dd>{{ $ontDevice->customer->pppoeAccount->username ?? '-' }}</dd></div>
                        <div><dt>Metode</dt><dd>{{ $methodLabels[$ontDevice->mapping_method] ?? $dash($ontDevice->mapping_method) }}</dd></div>
                        <div><dt>Confidence</dt><dd>{{ $ontDevice->mapping_confidence !== null ? $ontDevice->mapping_confidence.'%' : '-' }}</dd></div>
                        <div><dt>Dipetakan Oleh</dt><dd>{{ $ontDevice->mappedBy->name ?? '-' }}</dd></div>
                        <div><dt>Waktu</dt><dd>{{ $ontDevice->mapped_at?->format('d M Y H:i') ?? '-' }}</dd></div>
                    </dl>

                    @if ($canManageMapping)
                        <form method="POST" action="{{ route('ont-devices.mapping.destroy', $ontDevice) }}" onsubmit="return confirm('Lepas mapping ONT ini dari pelanggan?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="ont-danger-button">Lepas Mapping</button>
                        </form>
                    @endif
                @else
                    <p class="empty-inline"><span class="status-badge status-unmapped">Belum dipetakan</span> ONT ini masuk antrean pemeriksaan.</p>

                    @if ($canManageMapping)
                        @if ($candidates->isNotEmpty())
                            <h3 class="ont-subheading">Kandidat dari sesi PPPoE aktif</h3>
                            <ul class="ont-candidates">
                                @foreach ($candidates as $candidate)
                                    <li>
                                        <div>
                                            <strong>{{ $candidate['customer']->name }}</strong>
                                            <small class="ont-muted">{{ $candidate['customer']->customer_code }} · {{ $methodLabels[$candidate['method']] ?? $candidate['method'] }} · {{ $candidate['confidence'] }}%</small>
                                        </div>
                                        <form method="POST" action="{{ route('ont-devices.mapping.update', $ontDevice) }}" onsubmit="return confirm('Petakan ONT ini ke pelanggan kandidat tersebut?')">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="customer_id" value="{{ $candidate['customer']->id }}">
                                            <button type="submit" class="customer-filter-button">Petakan</button>
                                        </form>
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        <h3 class="ont-subheading">Pilih pelanggan secara manual</h3>
                        <form method="POST" action="{{ route('ont-devices.mapping.update', $ontDevice) }}" class="ont-manual-map" onsubmit="return confirm('Petakan ONT ini ke pelanggan yang dipilih?')">
                            @csrf
                            @method('PATCH')
                            <select name="customer_id" required aria-label="Pilih pelanggan">
                                <option value="">Pilih pelanggan tanpa ONT...</option>
                                @foreach ($assignableCustomers as $customer)
                                    <option value="{{ $customer->id }}" @selected((string) old('customer_id') === (string) $customer->id)>{{ $customer->name }} ({{ $customer->customer_code }})</option>
                                @endforeach
                            </select>
                            <button type="submit" class="customer-filter-button">Petakan</button>
                        </form>
                    @else
                        <p class="ont-muted">Hanya owner dan teknisi yang dapat mengubah mapping.</p>
                    @endif
                @endif
            </section>
        </div>

        <section class="detail-reference-card ont-detail-card">
            <div class="detail-card-heading"><h2>Posisi Topologi</h2>@if ($topologyPath)<a href="{{ route('network.topology', ['focus' => $ontDevice->network_node_id]) }}">Lihat di peta topologi</a>@endif</div>
            @if ($topologyPath)
                <p class="topo-path">
                    @foreach ($topologyPath as $step)
                        <span class="topo-type topo-type-{{ $step['type'] }}">{{ strtoupper(str_replace('_', ' ', $step['type'])) }}</span> {{ $step['name'] }}@if (! $loop->last) <span aria-hidden="true">→</span> @endif
                    @endforeach
                </p>
            @else
                <p class="empty-inline">ONT ini belum dipasang pada topologi jaringan.</p>
            @endif
        </section>

        <section class="detail-reference-card compact-table-card">
            <div class="detail-card-heading"><h2>Riwayat Status</h2></div>
            <table class="detail-reference-table">
                <thead><tr><th>Waktu</th><th>Perubahan</th><th>Last Inform</th><th>RX Power</th><th>Sumber</th></tr></thead>
                <tbody>
                    @forelse ($histories as $history)
                        <tr>
                            <td>{{ $history->observed_at->format('d M Y H:i:s') }}</td>
                            <td>
                                @if ($history->old_status)
                                    <span class="status-badge ont-status-{{ $history->old_status }}">{{ $statusLabels[$history->old_status] ?? $history->old_status }}</span> →
                                @endif
                                <span class="status-badge ont-status-{{ $history->new_status }}">{{ $statusLabels[$history->new_status] ?? $history->new_status }}</span>
                            </td>
                            <td>{{ $history->last_inform_at?->format('d M Y H:i:s') ?? '-' }}</td>
                            <td>{{ $history->rx_power_dbm !== null ? number_format((float) $history->rx_power_dbm, 2).' dBm' : '-' }}</td>
                            <td>{{ $history->source }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty-table">Belum ada riwayat perubahan status.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        <section class="detail-reference-card ont-detail-card">
            <div class="detail-card-heading">
                <h2>Task Perangkat</h2>
                <a href="{{ route('device-tasks.index') }}">Semua task</a>
            </div>

            @if (! $tasksEnabled)
                <div class="ont-banner ont-banner-warning" role="status">
                    Task perangkat dinonaktifkan. Set <code>GENIEACS_TASKS_ENABLED=true</code> untuk mengaktifkan summon, reboot, dan perubahan WiFi.
                </div>
            @elseif (! $canRunTask)
                <p class="empty-inline">Hanya Owner dan Teknisi yang dapat menjalankan task perangkat.</p>
            @endif

            @if ($tasksEnabled && $canRunTask)
                <div class="ont-task-actions">
                    <form method="POST" action="{{ route('ont-devices.tasks.summon', $ontDevice) }}"
                        onsubmit="return confirm('Kirim connection request ke ONT ini?')">
                        @csrf
                        <button type="submit" class="customer-filter-button" @disabled(in_array('summon', $openTaskTypes, true))>
                            Summon
                        </button>
                    </form>

                    <form method="POST" action="{{ route('ont-devices.tasks.reboot', $ontDevice) }}"
                        onsubmit="return confirm('Reboot ONT ini? Pastikan pelanggan siap terputus sejenak.')">
                        @csrf
                        <button type="submit" class="customer-filter-button" @disabled(in_array('reboot', $openTaskTypes, true))>
                            Reboot
                        </button>
                    </form>
                </div>

                @if ($profiles->isEmpty())
                    <p class="empty-inline">
                        Belum ada profil parameter terverifikasi untuk perangkat ini, sehingga perubahan WiFi belum dapat dijalankan.
                    </p>
                @else
                    <h3 class="ont-subheading">Ubah SSID &amp; Password WiFi</h3>
                    <p class="ont-muted">Password lama tidak pernah dibaca atau ditampilkan. Sistem hanya menulis nilai baru.</p>
                    <form method="POST" action="{{ route('ont-devices.tasks.wifi', $ontDevice) }}" class="ont-manual-map"
                        onsubmit="return confirm('Kirim perubahan SSID dan password WiFi ke ONT ini?')">
                        @csrf
                        <div class="form-grid">
                            <label>
                                <span>SSID</span>
                                <input type="text" name="ssid" value="{{ old('ssid') }}" maxlength="32" required>
                            </label>
                            <label>
                                <span>Password Baru</span>
                                <input type="password" name="password" value="" minlength="8" maxlength="63" required autocomplete="new-password">
                            </label>
                            <label>
                                <span>Profil Parameter</span>
                                <select name="profile_id">
                                    @foreach ($profiles as $profile)
                                        <option value="{{ $profile->id }}" @selected((string) old('profile_id') === (string) $profile->id)>
                                            {{ $profile->name }}
                                            @if ($profile->product_class) · {{ $profile->product_class }}@endif
                                            @if ($profile->pon_mode) · {{ strtoupper($profile->pon_mode) }}@endif
                                        </option>
                                    @endforeach
                                </select>
                            </label>
                        </div>
                        <button type="submit" class="customer-filter-button"
                            @disabled(in_array('set_wifi', $openTaskTypes, true))>
                            Kirim Perubahan WiFi
                        </button>
                        @error('ssid')<p class="ont-form-error">{{ $message }}</p>@enderror
                        @error('password')<p class="ont-form-error">{{ $message }}</p>@enderror
                        @error('profile_id')<p class="ont-form-error">{{ $message }}</p>@enderror
                        @error('type')<p class="ont-form-error">{{ $message }}</p>@enderror
                    </form>
                @endif
            @endif

            <div class="reference-table-panel customer-table-panel">
                <div class="overflow-x-auto">
                    <table class="customer-reference-table ont-table">
                        <thead>
                            <tr>
                                <th>Dibuat</th>
                                <th>Tipe</th>
                                <th>Status</th>
                                <th>Ringkasan</th>
                                <th>Percobaan</th>
                                <th>Pemohon</th>
                                <th>Tindakan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($recentTasks as $task)
                                <tr>
                                    <td>{{ $task->created_at->format('d M Y H:i:s') }}</td>
                                    <td>{{ $taskTypeLabels[$task->type] ?? $task->type }}</td>
                                    <td><span class="status-badge ont-status-{{ $task->status === 'verified' ? 'online' : ($task->status === 'failed' ? 'offline' : 'unknown') }}">{{ $taskStatusLabels[$task->status] ?? $task->status }}</span></td>
                                    <td>
                                        @if ($task->safe_summary['ssid'] ?? null)
                                            SSID: <strong>{{ $task->safe_summary['ssid'] }}</strong>
                                        @else
                                            {{ $taskTypeLabels[$task->type] ?? $task->type }}
                                        @endif
                                        @if ($task->error_message)
                                            <small class="ont-muted" title="{{ $task->error_message }}">{{ $task->error_message }}</small>
                                        @endif
                                    </td>
                                    <td>{{ $task->attempts }}</td>
                                    <td>{{ $task->requestedBy->name ?? '-' }}</td>
                                    <td>
                                        @if ($task->isOpen() && $canRunTask)
                                            <form method="POST" action="{{ route('device-tasks.cancel', $task) }}"
                                                onsubmit="return confirm('Batalkan task ini?')">
                                                @csrf
                                                <button type="submit" class="table-view-button">Batalkan</button>
                                            </form>
                                        @else
                                            -
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="empty-table">Belum ada task untuk perangkat ini.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</x-app-layout>
