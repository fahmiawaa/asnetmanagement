<x-app-layout>
    <div class="customer-list-page ont-list-page">
        <div class="reference-page-heading">
            <div>
                <h1>Task Perangkat</h1>
                <p class="ont-muted">Summon, reboot, dan perubahan WiFi yang dikirim ke GenieACS beserta hasil verifikasinya.</p>
            </div>
        </div>

        @if (! $tasksEnabled)
            <div class="ont-banner ont-banner-warning" role="status">
                Task perangkat dinonaktifkan melalui konfigurasi <code>GENIEACS_TASKS_ENABLED</code>.
            </div>
        @endif

        <div class="reference-content-card customer-list-card">
            <form method="GET" action="{{ route('device-tasks.index') }}" class="ont-list-filters">
                <select name="type" aria-label="Filter tipe task">
                    <option value="">Semua Tipe</option>
                    @foreach ($typeLabels as $value => $label)
                        <option value="{{ $value }}" @selected($type === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="status" aria-label="Filter status task">
                    <option value="">Semua Status</option>
                    @foreach ($statusLabels as $value => $label)
                        <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <button type="submit" class="customer-filter-button">Filter</button>
            </form>

            @error('status')<p class="ont-form-error">{{ $message }}</p>@enderror
            @error('type')<p class="ont-form-error">{{ $message }}</p>@enderror

            <div class="reference-table-panel customer-table-panel">
                <div class="overflow-x-auto">
                    <table class="customer-reference-table ont-table">
                        <thead>
                            <tr>
                                <th>Dibuat</th>
                                <th>Perangkat</th>
                                <th>Pelanggan</th>
                                <th>Tipe</th>
                                <th>Status</th>
                                <th>Ringkasan</th>
                                <th>Percobaan</th>
                                <th>Pemohon</th>
                                <th>Tindakan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($tasks as $task)
                                <tr>
                                    <td>{{ $task->created_at->format('d M Y H:i:s') }}</td>
                                    <td>
                                        <a href="{{ route('ont-devices.show', $task->ontDevice) }}">
                                            <strong>{{ $task->ontDevice->serial_number ?? $task->ontDevice->genieacs_device_id }}</strong>
                                        </a>
                                        <small class="ont-muted">{{ $task->ontDevice->genieacs_device_id }}</small>
                                    </td>
                                    <td>
                                        @if ($task->ontDevice->customer)
                                            <strong>{{ $task->ontDevice->customer->name }}</strong>
                                            <small class="ont-muted">{{ $task->ontDevice->customer->customer_code }}</small>
                                        @else
                                            <span class="status-badge status-unmapped">Belum dipetakan</span>
                                        @endif
                                    </td>
                                    <td>{{ $typeLabels[$task->type] ?? $task->type }}</td>
                                    <td><span class="status-badge ont-status-{{ $task->status === 'verified' ? 'online' : ($task->status === 'failed' ? 'offline' : 'unknown') }}">{{ $statusLabels[$task->status] ?? $task->status }}</span></td>
                                    <td>
                                        @if ($task->safe_summary['ssid'] ?? null)
                                            SSID: <strong>{{ $task->safe_summary['ssid'] }}</strong>
                                        @else
                                            {{ $typeLabels[$task->type] ?? $task->type }}
                                        @endif
                                        @if ($task->error_message)
                                            <small class="ont-muted" title="{{ $task->error_message }}">{{ $task->error_message }}</small>
                                        @endif
                                    </td>
                                    <td>{{ $task->attempts }}</td>
                                    <td>{{ $task->requestedBy->name ?? '-' }}</td>
                                    <td>
                                        <div class="customer-table-actions">
                                            @if ($task->isOpen() && in_array(Auth::user()->role, ['owner', 'teknisi'], true))
                                                <form method="POST" action="{{ route('device-tasks.cancel', $task) }}"
                                                    onsubmit="return confirm('Batalkan task ini?')">
                                                    @csrf
                                                    <button type="submit" class="table-view-button">Batalkan</button>
                                                </form>
                                            @else
                                                <a href="{{ route('ont-devices.show', $task->ontDevice) }}" class="table-view-button"><img src="{{ asset('images/icons/eye.svg') }}" alt=""> Detail</a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="9" class="empty-table">Belum ada task perangkat tercatat.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="customer-table-footer">
                    <span>Menampilkan {{ $tasks->firstItem() ?? 0 }}-{{ $tasks->lastItem() ?? 0 }} dari {{ $tasks->total() }} data</span>
                    <div>{{ $tasks->onEachSide(1)->links() }}</div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
