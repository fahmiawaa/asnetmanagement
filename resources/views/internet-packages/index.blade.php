<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Paket Internet') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">

                    <div style="margin-bottom: 16px;">
                        <a
                            href="{{ route('internet-packages.create') }}"
                            style="background-color: #2563eb; color: white; padding: 10px 16px; border-radius: 6px; text-decoration: none; display: inline-block;"
                        >
                            + Tambah Paket
                        </a>
                    </div>

                    <h3 class="text-lg font-semibold mb-4">
                        Daftar Paket Internet ASNet
                    </h3>

                    <form method="GET" action="{{ route('internet-packages.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-3 mb-6">
                        <input
                            type="search"
                            name="q"
                            value="{{ $search }}"
                            placeholder="Cari kode, nama, speed, profile..."
                            class="border-gray-300 rounded-md shadow-sm"
                        >

                        <select name="status" class="border-gray-300 rounded-md shadow-sm">
                            <option value="">Semua status</option>
                            <option value="active" @selected($status === 'active')>Aktif</option>
                            <option value="inactive" @selected($status === 'inactive')>Nonaktif</option>
                        </select>

                        <button type="submit" class="bg-gray-800 text-white px-4 py-2 rounded-md">
                            Cari / Filter
                        </button>

                        <a href="{{ route('internet-packages.index') }}" class="bg-gray-200 text-gray-800 px-4 py-2 rounded-md text-center">
                            Reset
                        </a>
                    </form>

                    @if ($packages->isEmpty())
                        <p class="text-gray-600">
                            Belum ada data paket internet.
                        </p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full border border-gray-200">
                                <thead>
                                    <tr class="bg-gray-100">
                                        <th class="border px-4 py-2 text-left">
                                            Kode Paket
                                        </th>

                                        <th class="border px-4 py-2 text-left">
                                            Nama Paket
                                        </th>

                                        <th class="border px-4 py-2 text-left">
                                            Download
                                        </th>

                                        <th class="border px-4 py-2 text-left">
                                            Upload
                                        </th>

                                        <th class="border px-4 py-2 text-left">
                                            Harga
                                        </th>

                                        <th class="border px-4 py-2 text-left">
                                            Profile MikroTik
                                        </th>

                                        <th class="border px-4 py-2 text-left">
                                            Status
                                        </th>
                                        <th class="border px-4 py-2 text-left">
                                            Sumber
                                        </th>
                                        <th class="border px-4 py-2 text-left">
                                            Aksi
                                        </th>
                                    </tr>
                                </thead>

                                <tbody>
                                    @foreach ($packages as $package)
                                        <tr>
                                            <td class="border px-4 py-2">
                                                {{ $package->package_code }}
                                            </td>

                                            <td class="border px-4 py-2">
                                                {{ $package->name }}
                                            </td>

                                            <td class="border px-4 py-2">
                                                {{ $package->download_speed }}
                                            </td>

                                            <td class="border px-4 py-2">
                                                {{ $package->upload_speed }}
                                            </td>

                                            <td class="border px-4 py-2">
                                                Rp {{ number_format((float) $package->price, 0, ',', '.') }}
                                            </td>

                                            <td class="border px-4 py-2">
                                                {{ $package->mikrotik_profile ?? '-' }}
                                            </td>

                                            <td class="border px-4 py-2">
                                                {{ $package->status }}
                                            </td>
                                            <td class="border px-4 py-2">
                                                @if ($package->sync_source === 'mikrotik')
                                                    MikroTik: {{ $package->mikrotikDevice->name ?? '-' }}
                                                    <br>
                                                    <small>{{ optional($package->last_synced_at)->format('d-m-Y H:i') ?? '-' }}</small>
                                                @else
                                                    Manual
                                                @endif
                                            </td>
                                            <td class="border px-4 py-2">
                                                <a
                                                    href="{{ route('internet-packages.edit', $package) }}"
                                                    style="background-color: #f59e0b; color: white; padding: 6px 10px; border-radius: 5px; text-decoration: none; display: inline-block;"
                                                >
                                                    Edit
                                                </a>

                                                <form
                                                    action="{{ route('internet-packages.destroy', $package) }}"
                                                    method="POST"
                                                    style="display: inline;"
                                                    onsubmit="return confirm('Apakah Anda yakin ingin menonaktifkan paket ini?')"
                                                >
                                                    @csrf
                                                    @method('DELETE')

                                                    <button
                                                        type="submit"
                                                        style="background-color: #dc2626; color: white; padding: 6px 10px; border-radius: 5px; border: none; cursor: pointer; margin-left: 5px;"
                                                    >
                                                        Nonaktifkan
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-4">{{ $packages->links() }}</div>
                    @endif

                </div>
            </div>
        </div>
    </div>
</x-app-layout>