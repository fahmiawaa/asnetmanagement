<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit Pelanggan') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">

                    <form method="POST" action="{{ route('customers.update', $customer) }}">
                        @csrf
                        @method('PUT')

                        <div class="mb-4">
                            <label for="customer_code" class="block font-medium text-sm text-gray-700">
                                Nomor Pelanggan
                            </label>

                            <input
                                type="text"
                                name="customer_code"
                                id="customer_code"
                                value="{{ old('customer_code', $customer->customer_code) }}"
                                class="block mt-1 w-full border-gray-300 rounded-md shadow-sm"
                                required
                            >

                            @error('customer_code')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="name" class="block font-medium text-sm text-gray-700">
                                Nama Pelanggan
                            </label>

                            <input
                                type="text"
                                name="name"
                                id="name"
                                value="{{ old('name', $customer->name) }}"
                                class="block mt-1 w-full border-gray-300 rounded-md shadow-sm"
                                required
                            >

                            @error('name')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="internet_package_id" class="block font-medium text-sm text-gray-700">
                                Paket Internet
                            </label>

                            <select
                                name="internet_package_id"
                                id="internet_package_id"
                                class="block mt-1 w-full border-gray-300 rounded-md shadow-sm"
                                required
                            >
                                <option value="">Pilih Paket Internet</option>

                                @foreach ($packages as $package)
                                    <option
                                        value="{{ $package->id }}"
                                        @selected(
                                            old('internet_package_id', $customer->internet_package_id) == $package->id
                                        )
                                    >
                                        {{ $package->name }}
                                        - Rp {{ number_format((float) $package->price, 0, ',', '.') }}
                                    </option>
                                @endforeach
                            </select>

                            @error('internet_package_id')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label
                                for="pppoe_account_id"
                                class="block font-medium text-sm text-gray-700"
                            >
                                Akun PPPoE
                            </label>

                            <select
                                name="pppoe_account_id"
                                id="pppoe_account_id"
                                class="block mt-1 w-full border-gray-300 rounded-md shadow-sm"
                            >
                                <option value="">
                                    Belum dipetakan
                                </option>

                                @foreach ($pppoeAccounts as $pppoeAccount)
                                    <option
                                        value="{{ $pppoeAccount->id }}"
                                        @selected(
                                            old(
                                                'pppoe_account_id',
                                                $customer->pppoe_account_id
                                            ) == $pppoeAccount->id
                                        )
                                    >
                                        {{ $pppoeAccount->username }}
                                        -
                                        {{ $pppoeAccount->profile ?? 'Tanpa profile' }}
                                        -
                                        {{ $pppoeAccount->mikrotikDevice->name ?? 'MikroTik' }}
                                    </option>
                                @endforeach
                            </select>

                            <p class="text-sm text-gray-500 mt-1">
                                Pilih akun PPPoE yang sesuai dengan pelanggan ini.
                            </p>

                            @error('pppoe_account_id')
                                <p class="text-red-600 text-sm mt-1">
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="whatsapp_number" class="block font-medium text-sm text-gray-700">
                                Nomor WhatsApp
                            </label>

                            <input
                                type="text"
                                name="whatsapp_number"
                                id="whatsapp_number"
                                value="{{ old('whatsapp_number', $customer->whatsapp_number) }}"
                                class="block mt-1 w-full border-gray-300 rounded-md shadow-sm"
                                required
                            >

                            @error('whatsapp_number')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="address" class="block font-medium text-sm text-gray-700">
                                Alamat
                            </label>

                            <textarea
                                name="address"
                                id="address"
                                rows="3"
                                class="block mt-1 w-full border-gray-300 rounded-md shadow-sm"
                                required
                            >{{ old('address', $customer->address) }}</textarea>

                            @error('address')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <div class="flex items-center justify-between gap-3">
                                <label class="block font-medium text-sm text-gray-700">
                                    Lokasi Pelanggan
                                </label>
                                <button
                                    type="button"
                                    id="use-current-location"
                                    class="text-sm text-blue-600"
                                >
                                    Gunakan lokasi perangkat
                                </button>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-1">
                                <div>
                                    <label for="latitude" class="block text-xs text-gray-500">Latitude</label>
                                    <input
                                        type="number"
                                        name="latitude"
                                        id="latitude"
                                        value="{{ old('latitude', $customer->latitude) }}"
                                        step="0.0000001"
                                        min="-90"
                                        max="90"
                                        placeholder="-6.2000000"
                                        class="block mt-1 w-full border-gray-300 rounded-md shadow-sm"
                                    >
                                </div>
                                <div>
                                    <label for="longitude" class="block text-xs text-gray-500">Longitude</label>
                                    <input
                                        type="number"
                                        name="longitude"
                                        id="longitude"
                                        value="{{ old('longitude', $customer->longitude) }}"
                                        step="0.0000001"
                                        min="-180"
                                        max="180"
                                        placeholder="106.8166667"
                                        class="block mt-1 w-full border-gray-300 rounded-md shadow-sm"
                                    >
                                </div>
                            </div>

                            <p class="text-sm text-gray-500 mt-1">
                                Hapus kedua nilai untuk menghilangkan titik pelanggan dari peta.
                            </p>
                            @error('latitude')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                            @error('longitude')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror

                            <div class="customer-location-picker">
                                <p class="customer-location-help">
                                    Peta dibuka di sekitar Desa Banyuputih, Kabupaten Batang. Klik posisi pelanggan, lalu geser pin untuk menyesuaikan titik secara presisi.
                                </p>
                                <div id="customer-location-map" class="customer-location-map"></div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="installation_date" class="block font-medium text-sm text-gray-700">
                                Tanggal Pemasangan
                            </label>

                            <input
                                type="date"
                                name="installation_date"
                                id="installation_date"
                                value="{{ old('installation_date', optional($customer->installation_date)->format('Y-m-d')) }}"
                                class="block mt-1 w-full border-gray-300 rounded-md shadow-sm"
                            >

                            @error('installation_date')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="due_day" class="block font-medium text-sm text-gray-700">
                                Tanggal Jatuh Tempo
                            </label>

                            <input
                                type="number"
                                name="due_day"
                                id="due_day"
                                value="{{ old('due_day', $customer->due_day) }}"
                                min="1"
                                max="31"
                                class="block mt-1 w-full border-gray-300 rounded-md shadow-sm"
                                required
                            >

                            @error('due_day')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="notes" class="block font-medium text-sm text-gray-700">
                                Catatan
                            </label>

                            <textarea
                                name="notes"
                                id="notes"
                                rows="3"
                                class="block mt-1 w-full border-gray-300 rounded-md shadow-sm"
                            >{{ old('notes', $customer->notes) }}</textarea>

                            @error('notes')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <button
                                type="submit"
                                style="background-color: #2563eb; color: white; padding: 10px 16px; border-radius: 6px; border: none; cursor: pointer;"
                            >
                                Simpan Perubahan
                            </button>

                            <a
                                href="{{ route('customers.index') }}"
                                style="background-color: #e5e7eb; color: #1f2937; padding: 10px 16px; border-radius: 6px; display: inline-block; text-decoration: none; margin-left: 8px;"
                            >
                                Batal
                            </a>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </div>

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const mapElement = document.getElementById('customer-location-map');
            const latitudeInput = document.getElementById('latitude');
            const longitudeInput = document.getElementById('longitude');

            if (!mapElement || !latitudeInput || !longitudeInput || !window.L) {
                return;
            }

            const defaultLocation = [-6.9682295, 109.9462228];
            const initialLatitude = Number.parseFloat(latitudeInput.value);
            const initialLongitude = Number.parseFloat(longitudeInput.value);
            const hasInitialLocation = Number.isFinite(initialLatitude) && Number.isFinite(initialLongitude);
            const initialLocation = hasInitialLocation
                ? [initialLatitude, initialLongitude]
                : defaultLocation;
            const map = L.map(mapElement).setView(initialLocation, hasInitialLocation ? 16 : 15);
            let marker = null;

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors',
            }).addTo(map);

            const setLocation = (latitude, longitude) => {
                const lat = Number(latitude).toFixed(7);
                const lng = Number(longitude).toFixed(7);
                latitudeInput.value = lat;
                longitudeInput.value = lng;

                if (!marker) {
                    marker = L.marker([lat, lng], { draggable: true }).addTo(map);
                    marker.bindTooltip('Geser pin untuk menyesuaikan lokasi');
                    marker.on('dragend', () => {
                        const position = marker.getLatLng();
                        setLocation(position.lat, position.lng);
                    });
                } else {
                    marker.setLatLng([lat, lng]);
                }

                map.setView([lat, lng], Math.max(map.getZoom(), 16));
            };

            if (hasInitialLocation) {
                setLocation(initialLatitude, initialLongitude);
            }

            map.on('click', (event) => setLocation(event.latlng.lat, event.latlng.lng));

            document.getElementById('use-current-location')?.addEventListener('click', function () {
                if (!navigator.geolocation) {
                    alert('Browser tidak mendukung pembacaan lokasi.');
                    return;
                }

                navigator.geolocation.getCurrentPosition(
                    (position) => setLocation(position.coords.latitude, position.coords.longitude),
                    () => alert('Lokasi tidak dapat dibaca. Pastikan izin lokasi browser sudah diberikan.'),
                    { enableHighAccuracy: true, timeout: 10000 }
                );
            });
        });
    </script>
</x-app-layout>