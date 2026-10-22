<x-app-layout>
    <div class="module-page user-management-page">
        <div class="module-page-heading"><h1>Manajemen Pengguna</h1><a href="{{ route('users.create') }}" class="reference-primary-action"><span>+</span> Tambah User</a></div>
        <div class="module-table-card user-table-card">
            <form method="GET" action="{{ route('users.index') }}" class="user-filter-row">
                <label class="module-search-field"><img src="{{ asset('images/icons/search.svg') }}" alt=""><input type="search" name="q" value="{{ $search }}" placeholder="Cari pengguna..."></label>
                <select name="role"><option value="">Semua Role</option>@foreach (['owner' => 'Owner', 'admin' => 'Admin', 'teknisi' => 'Teknisi', 'pelanggan' => 'Pelanggan'] as $value => $label)<option value="{{ $value }}" @selected($role === $value)>{{ $label }}</option>@endforeach</select>
                <select name="active"><option value="">Semua Status</option><option value="1" @selected($active === '1')>Aktif</option><option value="0" @selected($active === '0')>Nonaktif</option></select>
                <button type="submit" class="module-filter-button">Filter</button>
            </form>
            <div class="module-table-scroll"><table class="module-reference-table user-reference-table"><thead><tr><th>Nama</th><th>Email</th><th>Role</th><th>Pelanggan Terkait</th><th>Status Aktif</th><th>Aktivitas Terakhir</th><th>Aksi</th></tr></thead><tbody>
                @forelse ($users as $user)
                    <tr><td>{{ $user->name }}</td><td>{{ $user->email }}</td><td>{{ ucfirst($user->role) }}</td><td>{{ $user->customer ? 'ID: ' . $user->customer->customer_code : '-' }}</td><td>
                        @if ($user->is(auth()->user()))
                            <span class="user-status-toggle {{ $user->active ? 'is-active' : '' }}"><i></i>{{ $user->active ? 'Aktif' : 'Nonaktif' }}</span>
                        @else
                            <form method="POST" action="{{ route('users.toggle', $user) }}" onsubmit="return confirm(@js('Ubah status akun ' . $user->name . '?'))">
                                @csrf
                                <button type="submit" class="user-status-toggle user-status-trigger {{ $user->active ? 'is-active' : '' }}" title="Klik untuk mengubah status akun">
                                    <i></i><span>{{ $user->active ? 'Aktif' : 'Nonaktif' }}</span>
                                </button>
                            </form>
                        @endif
                    </td><td>{{ optional($user->updated_at)->diffForHumans() }}</td><td><div class="user-actions"><a href="{{ route('users.edit', $user) }}" class="icon-action"><img src="{{ asset('images/icons/edit.svg') }}" alt=""></a></div></td></tr>
                @empty
                    <tr><td colspan="7" class="empty-table">Belum ada data pengguna.</td></tr>
                @endforelse
            </tbody></table></div>
            <div class="module-table-footer"><span>{{ $users->firstItem() ?? 0 }} dari {{ $users->total() }} pengguna</span>{{ $users->links() }}</div>
        </div>
    </div>
</x-app-layout>
