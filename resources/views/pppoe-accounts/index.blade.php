<x-app-layout>
    <div class="module-page pppoe-management-page">
        <div class="module-page-heading"><h1>Manajemen Akun PPPoE</h1><div class="module-heading-actions">@if (in_array(Auth::user()->role, ['owner', 'teknisi'], true))<form method="POST" action="{{ route('pppoe-accounts.sync') }}">@csrf<button type="submit" class="reference-secondary-action">⟳ Sinkronkan MikroTik</button></form>@endif<a href="{{ route('customers.create') }}" class="reference-primary-action"><span>+</span> Tambah Akun PPPoE</a></div></div>
        @if (session('success'))<div class="flash flash-success">{{ session('success') }}</div>@endif
        @if (session('error'))<div class="flash flash-error">{{ session('error') }}</div>@endif
        <div class="module-table-card pppoe-table-card">
            <form method="GET" action="{{ route('pppoe-accounts.index') }}" class="pppoe-filter-row">
                <label><span>Perangkat MikroTik</span><select name="device_id"><option value="">Semua Perangkat</option>@foreach ($devices as $device)<option value="{{ $device->id }}" @selected((string) $deviceId === (string) $device->id)>{{ $device->name }}</option>@endforeach</select></label>
                <label class="module-search-field"><img src="{{ asset('images/icons/search.svg') }}" alt=""><input type="search" name="q" value="{{ $search }}" placeholder="Cari Username atau Pelanggan..."></label>
                <select name="presence"><option value="">Semua Status</option><option value="present" @selected($presence === 'present')>Provisioned</option><option value="missing" @selected($presence === 'missing')>Belum Ditemukan</option></select>
                <button type="submit" class="module-filter-button">☰ Filter</button>
            </form>
            <div class="module-table-scroll"><table class="module-reference-table pppoe-reference-table"><thead><tr><th>Username</th><th>Pelanggan</th><th>Profil Layanan</th><th>Perangkat MikroTik</th><th>Alamat IP</th><th>Sinkronisasi Terakhir</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
                @forelse ($accounts as $account)
                    @php
                        $accountStatus = !$account->is_present ? 'missing' : ($account->disabled ? 'disabled' : ($account->provision_status === 'failed' ? 'failed' : 'provisioned'));
                    @endphp
                    <tr><td>{{ $account->username }}</td><td>{{ $account->customer->name ?? '-' }}</td><td>{{ $account->profile ?? $account->service ?? '-' }}</td><td>{{ $account->mikrotikDevice->name ?? '-' }}</td><td>{{ $account->remote_address ?? $account->local_address ?? '-' }}</td><td>{{ optional($account->last_synced_at)->format('d/m/Y H:i') ?? '-' }}</td><td><span class="pppoe-status-badge {{ $accountStatus }}"><i></i>{{ match($accountStatus) {'provisioned' => 'Provisioned', 'disabled' => 'Disabled', 'failed' => 'Failed', default => 'Missing'} }}</span></td><td><div class="pppoe-actions">
                        <a href="{{ route('pppoe-accounts.edit', $account) }}" class="pppoe-icon-action edit" title="Edit"><img src="{{ asset('images/icons/edit.svg') }}" alt=""></a>
                        @if (!$account->is_present || in_array($account->provision_status, ['pending', 'failed'], true))<form action="{{ route('pppoe-accounts.provision', $account) }}" method="POST">@csrf<button class="pppoe-icon-action sync" title="Provision ulang">⟳</button></form>@elseif ($account->disabled)<form action="{{ route('pppoe-accounts.enable', $account) }}" method="POST">@csrf<button class="pppoe-icon-action enable" title="Aktifkan">▶</button></form>@else<form action="{{ route('pppoe-accounts.disable', $account) }}" method="POST">@csrf<button class="pppoe-icon-action disable" title="Nonaktifkan">−</button></form>@endif
                    </div></td></tr>
                @empty
                    <tr><td colspan="8" class="empty-table">Belum ada data akun PPPoE.</td></tr>
                @endforelse
            </tbody></table></div>
            <div class="module-table-footer"><span>Menampilkan {{ $accounts->firstItem() ?? 0 }}-{{ $accounts->lastItem() ?? 0 }} dari {{ $accounts->total() }} akun</span>{{ $accounts->links() }}</div>
        </div>
    </div>
    <script>window.setTimeout(function () { window.location.reload(); }, 60000);</script>
</x-app-layout>
