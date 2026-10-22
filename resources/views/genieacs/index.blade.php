<x-app-layout>
    <div class="customer-list-page ont-list-page">
        <div class="reference-page-heading">
            <div>
                <h1>GenieACS</h1>
                <p class="ont-muted">Konfigurasi NBI internal, uji koneksi, dan sinkronisasi snapshot ONT.</p>
            </div>
        </div>

        @if ($syncStale)
            <div class="ont-banner ont-banner-warning" role="status">
                Sinkronisasi GenieACS sudah lebih dari {{ config('genieacs.sync_max_age_minutes') }} menit atau belum pernah berjalan.
            </div>
        @endif

        <div class="ont-stats-grid">
            <div class="ont-stat-card"><span>Server Terdaftar</span><strong>{{ $servers->count() }}</strong></div>
            <div class="ont-stat-card"><span>Server Aktif</span><strong>{{ $servers->where('is_active', true)->count() }}</strong></div>
            <div class="ont-stat-card ont-stat-online"><span>Total ONT Tersinkron</span><strong>{{ $ontTotal }}</strong></div>
            <div class="ont-stat-card ont-stat-warning"><span>Koneksi Gagal</span><strong>{{ $servers->where('connection_status', 'failed')->count() }}</strong></div>
        </div>

        <div class="reference-content-card customer-list-card">
            <div class="reference-table-panel customer-table-panel">
                <div class="overflow-x-auto">
                    <table class="customer-reference-table ont-table">
                        <thead>
                            <tr>
                                <th>Nama</th>
                                <th>URL NBI</th>
                                <th>Autentikasi</th>
                                <th>Status</th>
                                <th>Last Sync</th>
                                <th>ONT</th>
                                <th>Tindakan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($servers as $server)
                                <tr>
                                    <td>
                                        <strong>{{ $server->name }}</strong>
                                        @unless ($server->is_active)
                                            <small class="ont-muted">Nonaktif</small>
                                        @endunless
                                    </td>
                                    <td>{{ $server->base_url }}</td>
                                    <td>
                                        @if ($server->username)
                                            {{ $server->username }}
                                        @else
                                            Tanpa autentikasi
                                        @endif
                                    </td>
                                    <td><span class="status-badge ont-status-{{ $server->connection_status === 'connected' ? 'online' : ($server->connection_status === 'failed' ? 'offline' : 'unknown') }}">{{ ucfirst($server->connection_status) }}</span></td>
                                    <td>
                                        @if ($server->last_sync_at)
                                            <span title="{{ $server->last_sync_at->format('d M Y H:i:s') }}">{{ $server->last_sync_at->diffForHumans() }}</span>
                                        @else
                                            Belum pernah
                                        @endif
                                        @if ($server->last_error_at)
                                            <small class="ont-muted" title="{{ $server->last_error_message }}">Terakhir gagal {{ $server->last_error_at->diffForHumans() }}</small>
                                        @endif
                                    </td>
                                    <td>{{ $server->ont_devices_count }}</td>
                                    <td>
                                        <div class="customer-table-actions">
                                            @if (Auth::user()->role === 'owner')
                                                <form method="POST" action="{{ route('genieacs.test') }}">
                                                    @csrf
                                                    <input type="hidden" name="server_id" value="{{ $server->id }}">
                                                    <button type="submit" class="table-view-button">Uji Koneksi</button>
                                                </form>
                                                <form method="POST" action="{{ route('genieacs.sync') }}">
                                                    @csrf
                                                    <input type="hidden" name="server_id" value="{{ $server->id }}">
                                                    <button type="submit" class="table-view-button">Sinkronkan</button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="empty-table">Belum ada server GenieACS. Tambahkan konfigurasi NBI di bawah atau jalankan <code>php artisan genieacs:configure</code>.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        @if (Auth::user()->role === 'owner')
            <div class="reference-content-card customer-list-card">
                <h2>Simpan Konfigurasi Server</h2>
                <form method="POST" action="{{ route('genieacs.store') }}" class="ont-list-filters">
                    @csrf
                    <label class="customer-search-field">
                        <input type="text" name="name" value="{{ old('name') }}" placeholder="Nama server, contoh: GenieACS Utama" maxlength="100" required>
                    </label>
                    <label class="customer-search-field">
                        <input type="url" name="base_url" value="{{ old('base_url') }}" placeholder="http://genieacs:7557" maxlength="255" required>
                    </label>
                    <label class="customer-search-field">
                        <input type="text" name="username" value="{{ old('username') }}" placeholder="Username Basic Auth (opsional)" maxlength="100" autocomplete="off">
                    </label>
                    <label class="customer-search-field">
                        <input type="password" name="password" value="" placeholder="Password Basic Auth (opsional)" maxlength="255" autocomplete="new-password">
                    </label>
                    <label class="ont-checkbox">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true))>
                        <span>Aktif</span>
                    </label>
                    <button type="submit" class="customer-filter-button">Simpan</button>
                </form>

                @if ($errors->any())
                    <ul class="ont-form-error">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                @endif

                <p class="ont-muted">
                    Password disimpan terenkripsi dan tidak pernah ditampilkan kembali. NBI GenieACS hanya boleh
                    diakses dari jaringan internal.
                </p>
            </div>
        @endif
    </div>
</x-app-layout>
