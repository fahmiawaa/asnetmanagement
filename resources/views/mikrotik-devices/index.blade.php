<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Perangkat MikroTik') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">

                    @if (session('success'))
                        <div class="mb-4 p-4 bg-green-100 text-green-800 rounded-md">
                            {{ session('success') }}
                        </div>
                    @endif

                    @if (session('error'))
                        <div class="mb-4 p-4 bg-red-100 text-red-800 rounded-md">
                            {{ session('error') }}
                        </div>
                    @endif

                    @if (session('mikrotik_sync_result'))
                        @php($syncResult = session('mikrotik_sync_result'))
                        <section class="mikrotik-sync-result" aria-label="Hasil import MikroTik">
                            <div class="mikrotik-sync-result-heading">
                                <div>
                                    <strong>Hasil Import {{ $syncResult['device'] }}</strong>
                                    <span>Koneksi berhasil dan data dibaca langsung dari router.</span>
                                </div>
                                <span class="pppoe-status-badge provisioned"><i></i>Read-only berhasil</span>
                            </div>
                            <div class="mikrotik-sync-result-grid">
                                <article><span>PPP Profile</span><strong>{{ $syncResult['profiles']['created'] + $syncResult['profiles']['updated'] }}</strong><small>{{ $syncResult['profiles']['created'] }} baru · {{ $syncResult['profiles']['updated'] }} diperbarui</small></article>
                                <article><span>PPP Secret</span><strong>{{ $syncResult['secrets']['created'] + $syncResult['secrets']['updated'] }}</strong><small>{{ $syncResult['secrets']['created'] }} baru · {{ $syncResult['secrets']['updated'] }} diperbarui</small></article>
                                <article><span>Sesi aktif</span><strong>{{ $syncResult['sessions']['active'] }}</strong><small>{{ $syncResult['sessions']['created'] }} baru · {{ $syncResult['sessions']['updated'] }} diperbarui</small></article>
                            </div>
                            <p>Profile baru masuk sebagai paket <b>inactive</b> dengan harga Rp0. Atur harga lalu aktifkan paket sebelum dipakai untuk billing.</p>
                        </section>
                    @endif

                    <div style="margin-bottom: 16px;">
                        <a
                            href="{{ route('mikrotik-devices.create') }}"
                            style="background-color: #2563eb; color: white; padding: 10px 16px; border-radius: 6px; text-decoration: none; display: inline-block;"
                        >
                            + Tambah Perangkat
                        </a>
                    </div>

                    <h3 class="text-lg font-semibold mb-4">
                        Daftar Perangkat MikroTik
                    </h3>

                    <form method="GET" action="{{ route('mikrotik-devices.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-3 mb-6">
                        <input
                            type="search"
                            name="q"
                            value="{{ $search }}"
                            placeholder="Cari nama, host, username..."
                            class="border-gray-300 rounded-md shadow-sm"
                        >

                        <select name="status" class="border-gray-300 rounded-md shadow-sm">
                            <option value="">Semua status</option>
                            <option value="unknown" @selected($status === 'unknown')>Unknown</option>
                            <option value="connected" @selected($status === 'connected')>Connected</option>
                            <option value="failed" @selected($status === 'failed')>Failed</option>
                            <option value="inactive" @selected($status === 'inactive')>Inactive</option>
                        </select>

                        <button type="submit" class="bg-gray-800 text-white px-4 py-2 rounded-md">
                            Cari / Filter
                        </button>

                        <a href="{{ route('mikrotik-devices.index') }}" class="bg-gray-200 text-gray-800 px-4 py-2 rounded-md text-center">
                            Reset
                        </a>
                    </form>

                    @if ($devices->isEmpty())
                        <p class="text-gray-600">
                            Belum ada perangkat MikroTik.
                        </p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full border border-gray-200">
                                <thead>
                                    <tr class="bg-gray-100">
                                        <th class="border px-4 py-2 text-left">
                                            Nama
                                        </th>

                                        <th class="border px-4 py-2 text-left">
                                            Host
                                        </th>

                                        <th class="border px-4 py-2 text-left">
                                            Port API
                                        </th>

                                        <th class="border px-4 py-2 text-left">
                                            SSL
                                        </th>

                                        <th class="border px-4 py-2 text-left">
                                            Username
                                        </th>

                                        <th class="border px-4 py-2 text-left">
                                            Status Koneksi
                                        </th>

                                        <th class="border px-4 py-2 text-left">
                                            Sinkronisasi Terakhir
                                        </th>

                                        <th class="border px-4 py-2 text-left">
                                            Aksi
                                        </th>
                                    </tr>
                                </thead>

                                <tbody>
                                    @foreach ($devices as $device)
                                        <tr>
                                            <td class="border px-4 py-2">
                                                {{ $device->name }}
                                            </td>

                                            <td class="border px-4 py-2">
                                                {{ $device->host }}
                                            </td>

                                            <td class="border px-4 py-2">
                                                {{ $device->api_port }}
                                            </td>

                                            <td class="border px-4 py-2">
                                                {{ $device->use_ssl ? 'Ya' : 'Tidak' }}
                                            </td>

                                            <td class="border px-4 py-2">
                                                {{ $device->username }}
                                            </td>

                                            <td class="border px-4 py-2">
                                                {{ $device->connection_status }}
                                            </td>

                                            <td class="border px-4 py-2">
                                                {{ optional($device->last_sync_at)->format('d-m-Y H:i') ?? '-' }}
                                            </td>

                                            <td class="border px-4 py-2">
                                                <a
                                                    href="{{ route('mikrotik-devices.edit', $device) }}"
                                                    style="background-color: #f59e0b; color: white; padding: 6px 10px; border-radius: 5px; text-decoration: none; display: inline-block;"
                                                >
                                                    Edit
                                                </a>

                                                <form
                                                    action="{{ route('mikrotik-devices.test-connection', $device) }}"
                                                    method="POST"
                                                    style="display: inline-block; margin-left: 6px;"
                                                >
                                                    @csrf

                                                    <button
                                                        type="submit"
                                                        style="background-color: #2563eb; color: white; padding: 6px 10px; border-radius: 5px; border: none; cursor: pointer;"
                                                    >
                                                        Uji Koneksi
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-4">{{ $devices->links() }}</div>
                    @endif

                </div>
            </div>
        </div>
    </div>
</x-app-layout>