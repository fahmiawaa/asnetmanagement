<x-app-layout>
    <div class="module-page active-sessions-page">
        <div class="module-page-heading"><h1>Sesi PPPoE Aktif</h1><span class="active-session-count">{{ $sessions->count() }} Sesi Aktif</span></div>
        <div class="active-sessions-card">
            <div class="active-sessions-toolbar">
                <form method="GET" action="{{ route('pppoe-accounts.sessions') }}" class="session-device-form">
                    <select name="device_id"><option value="">MikroTik Router: [Semua Perangkat]</option>@foreach ($devices as $device)<option value="{{ $device->id }}" @selected((string) $deviceId === (string) $device->id)>{{ $device->name }}</option>@endforeach</select>
                    <button type="submit">⟳ Segarkan</button>
                </form>
                <strong>{{ $sessions->count() }} Sesi Aktif</strong>
            </div>
            @if ($error)<div class="module-error">{{ $error }}</div>@endif
            <div class="module-table-scroll active-session-table-wrap"><table class="module-reference-table active-session-table"><thead><tr><th>Nama Pengguna</th><th>Pelanggan</th><th>Perangkat</th><th>Alamat IP</th><th>Waktu Aktif</th><th>ID Pemanggil</th><th>Profil</th><th>Sinkron Terakhir</th><th>Aksi</th></tr></thead><tbody>
                @forelse ($sessions as $session)
                    <tr><td>{{ $session['name'] ?? '-' }}</td><td>{{ $session['customer'] ?? '-' }}</td><td>{{ $session['device'] ?? '-' }}</td><td>{{ $session['address'] ?? '-' }}</td><td>{{ $session['uptime'] ?? '-' }}</td><td>{{ $session['caller-id'] ?? '-' }}</td><td>{{ $session['profile'] ?? $session['service'] ?? '-' }}</td><td>{{ optional($session['last-synced-at'] ?? null)->format('d/m/Y H:i:s') ?? '-' }}</td><td><button type="button" class="disconnect-session-button" disabled>Putuskan</button></td></tr>
                @empty
                    <tr><td colspan="9" class="empty-table">{{ $deviceId ? 'Tidak ada sesi PPPoE aktif pada perangkat ini.' : 'Belum ada snapshot sesi aktif. Jalankan sinkronisasi MikroTik.' }}</td></tr>
                @endforelse
            </tbody></table></div>
        </div>
    </div>
</x-app-layout>
