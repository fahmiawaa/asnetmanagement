<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Tambah Pelanggan') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">

                    <form method="POST" action="{{ route('customers.store') }}">
                        @csrf

                        <div class="mb-4">
                            <label for="customer_code" class="block font-medium text-sm text-gray-700">
                                Nomor Pelanggan
                            </label>

                            <input
                                type="text"
                                name="customer_code"
                                id="customer_code"
                                value="{{ old('customer_code') }}"
                                placeholder="Contoh: AS001"
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
                                value="{{ old('name') }}"
                                class="block mt-1 w-full border-gray-300 rounded-md shadow-sm"
                                required
                            >

                            @error('name')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-4">
                            @php
                                $selectedMikrotikDeviceId = (string) old(
                                    'mikrotik_device_id',
                                    $defaultMikrotikDeviceId
                                );
                            @endphp

                            <label for="mikrotik_device_id" class="block font-medium text-sm text-gray-700">
                                Perangkat MikroTik
                            </label>

                            <select
                                name="mikrotik_device_id"
                                id="mikrotik_device_id"
                                class="block mt-1 w-full border-gray-300 rounded-md shadow-sm"
                                required
                            >
                                @if ($mikrotikDevices->count() !== 1)
                                    <option value="">Pilih MikroTik</option>
                                @endif

                                @foreach ($mikrotikDevices as $device)
                                    <option
                                        value="{{ $device->id }}"
                                        @selected($selectedMikrotikDeviceId === (string) $device->id)
                                    >
                                        {{ $device->name }} · {{ $device->host }} · {{ $device->connection_status }}
                                    </option>
                                @endforeach
                            </select>

                            @if ($mikrotikDevices->count() === 1)
                                <p class="text-sm text-green-700 mt-1">
                                    MikroTik dipilih otomatis karena hanya ada satu perangkat aktif.
                                </p>
                            @elseif ($mikrotikDevices->isEmpty())
                                <p class="text-sm text-red-600 mt-1">
                                    Belum ada perangkat MikroTik aktif. Tambahkan atau aktifkan perangkat terlebih dahulu.
                                </p>
                            @else
                                <p class="text-sm text-gray-500 mt-1">
                                    Paket internet akan disesuaikan dengan MikroTik yang dipilih.
                                </p>
                            @endif

                            @error('mikrotik_device_id')
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
                                <option value="">
                                    {{ $selectedMikrotikDeviceId !== '' ? 'Pilih Paket Internet' : 'Pilih MikroTik terlebih dahulu' }}
                                </option>

                                @foreach ($packages->where(
                                    'mikrotik_device_id',
                                    (int) $selectedMikrotikDeviceId
                                ) as $package)
                                    <option
                                        value="{{ $package->id }}"
                                        data-mikrotik-profile="{{ $package->mikrotik_profile }}"
                                        @selected(old('internet_package_id') == $package->id)
                                    >
                                        {{ $package->name }}
                                        - Rp {{ number_format((float) $package->price, 0, ',', '.') }}
                                    </option>
                                @endforeach
                            </select>

                            @error('internet_package_id')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror

                            <p
                                id="internet-package-empty-message"
                                class="text-sm text-amber-700 mt-1"
                                hidden
                            >
                                MikroTik ini belum memiliki paket aktif yang tersinkron.
                            </p>
                        </div>

                        <div
                            x-data="{ createPppoe: {{ old('create_pppoe_account') ? 'true' : 'false' }} }"
                            class="customer-pppoe-box mb-5"
                        >
                            <label class="flex items-start gap-3 cursor-pointer">
                                <input
                                    type="checkbox"
                                    name="create_pppoe_account"
                                    value="1"
                                    x-model="createPppoe"
                                    class="mt-1 rounded border-gray-300"
                                >
                                <span>
                                    <strong class="block text-sm text-gray-800">Buat akun PPPoE untuk pelanggan ini</strong>
                                    <small class="block mt-1 text-gray-500">
                                        Akun dapat disimpan ke web dan, jika diaktifkan, langsung dibuat pada MikroTik.
                                    </small>
                                </span>
                            </label>

                            <div x-cloak x-show="createPppoe" x-transition class="customer-pppoe-fields mt-4">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label for="pppoe_service" class="block font-medium text-sm text-gray-700">
                                            Service
                                        </label>
                                        <input
                                            type="text"
                                            name="pppoe_service"
                                            id="pppoe_service"
                                            value="{{ old('pppoe_service', 'pppoe') }}"
                                            class="block mt-1 w-full border-gray-300 rounded-md shadow-sm"
                                        >
                                    </div>

                                    <div>
                                        <label for="pppoe_username" class="block font-medium text-sm text-gray-700">
                                            Username PPPoE
                                        </label>
                                        <input
                                            type="text"
                                            name="pppoe_username"
                                            id="pppoe_username"
                                            value="{{ old('pppoe_username') }}"
                                            autocomplete="username"
                                            class="block mt-1 w-full border-gray-300 rounded-md shadow-sm"
                                            :required="createPppoe"
                                        >
                                        @error('pppoe_username')
                                            <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div>
                                        <label for="pppoe_password" class="block font-medium text-sm text-gray-700">
                                            Password PPPoE
                                        </label>
                                        <input
                                            type="password"
                                            name="pppoe_password"
                                            id="pppoe_password"
                                            autocomplete="new-password"
                                            class="block mt-1 w-full border-gray-300 rounded-md shadow-sm"
                                            :required="createPppoe"
                                        >
                                        <p class="text-xs text-gray-500 mt-1">Disimpan terenkripsi di database.</p>
                                        @error('pppoe_password')
                                            <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div>
                                        <label for="pppoe_profile" class="block font-medium text-sm text-gray-700">
                                            Profile MikroTik
                                        </label>
                                        <input
                                            type="text"
                                            name="pppoe_profile"
                                            id="pppoe_profile"
                                            value="{{ old('pppoe_profile') }}"
                                            placeholder="Contoh: 10M"
                                            class="block mt-1 w-full border-gray-300 rounded-md shadow-sm"
                                        >
                                    </div>
                                </div>

                                <label class="flex items-start gap-3 mt-4 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        name="provision_pppoe"
                                        value="1"
                                        @checked(old('provision_pppoe', true))
                                        class="mt-1 rounded border-gray-300"
                                    >
                                    <span>
                                        <strong class="block text-sm text-gray-800">Kirim langsung ke MikroTik</strong>
                                        <small class="block mt-1 text-gray-500">
                                            Membuat secret PPPoE secara otomatis. Aktifkan MIKROTIK_ALLOW_REAL_ACTIONS=true setelah koneksi MikroTik diuji.
                                        </small>
                                    </span>
                                </label>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="whatsapp_number" class="block font-medium text-sm text-gray-700">
                                Nomor WhatsApp
                            </label>

                            <input
                                type="text"
                                name="whatsapp_number"
                                id="whatsapp_number"
                                value="{{ old('whatsapp_number') }}"
                                placeholder="Contoh: 08123456789"
                                class="block mt-1 w-full border-gray-300 rounded-md shadow-sm"
                                required
                            >
                        </div>

                        <div class="mb-4 customer-address-form">
                            <label class="block font-medium text-sm text-gray-700">
                                Alamat Wilayah
                            </label>
                            <div class="customer-address-select-grid mt-1" data-region-picker>
                                <select id="address_province" class="border-gray-300 rounded-md shadow-sm" aria-label="Provinsi">
                                    <option value="">Memuat provinsi...</option>
                                </select>
                                <select id="address_regency" class="border-gray-300 rounded-md shadow-sm" aria-label="Kabupaten atau kota" disabled>
                                    <option value="">Pilih kabupaten/kota</option>
                                </select>
                                <select id="address_district" class="border-gray-300 rounded-md shadow-sm" aria-label="Kecamatan" disabled>
                                    <option value="">Pilih kecamatan</option>
                                </select>
                                <select id="address_village" class="border-gray-300 rounded-md shadow-sm" aria-label="Desa atau kelurahan" disabled>
                                    <option value="">Pilih desa/kelurahan</option>
                                </select>
                            </div>
                            <p class="customer-address-loading" data-region-status>Memuat daftar wilayah...</p>
                            <label for="address" class="block mt-3 font-medium text-sm text-gray-700">
                                Detail Alamat Manual
                            </label>

                            <textarea
                                name="address"
                                id="address"
                                rows="3"
                                class="block mt-1 w-full border-gray-300 rounded-md shadow-sm"
                                required
                            >{{ old('address') }}</textarea>
                            <p class="text-xs text-gray-500 mt-1">Isi nama jalan, RT/RW, nomor rumah, dusun, atau patokan. Wilayah pilihan akan disimpan bersama detail ini.</p>
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
                                        value="{{ old('latitude') }}"
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
                                        value="{{ old('longitude') }}"
                                        step="0.0000001"
                                        min="-180"
                                        max="180"
                                        placeholder="106.8166667"
                                        class="block mt-1 w-full border-gray-300 rounded-md shadow-sm"
                                    >
                                </div>
                            </div>

                            <p class="text-sm text-gray-500 mt-1">
                                Isi kedua koordinat agar pelanggan muncul di menu Map Pelanggan.
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
                                value="{{ old('installation_date') }}"
                                class="block mt-1 w-full border-gray-300 rounded-md shadow-sm"
                            >
                        </div>

                        <div class="mb-4">
                            <label for="due_day" class="block font-medium text-sm text-gray-700">
                                Tanggal Jatuh Tempo
                            </label>

                            <input
                                type="number"
                                name="due_day"
                                id="due_day"
                                value="{{ old('due_day', 10) }}"
                                min="1"
                                max="31"
                                class="block mt-1 w-full border-gray-300 rounded-md shadow-sm"
                                required
                            >
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
                            >{{ old('notes') }}</textarea>
                        </div>

                        <div class="flex items-center gap-3">
                            <button
                                type="submit"
                                style="background-color: #2563eb; color: white; padding: 10px 16px; border-radius: 6px; border: none; cursor: pointer; display: inline-block;"
                            >
                                Simpan Pelanggan
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
            const mikrotikSelect = document.getElementById('mikrotik_device_id');
            const packageSelect = document.getElementById('internet_package_id');
            const profileInput = document.getElementById('pppoe_profile');
            const emptyMessage = document.getElementById('internet-package-empty-message');
            const packages = {{ Illuminate\Support\Js::from(
                $packages->map(fn ($package) => [
                    'id' => (string) $package->id,
                    'mikrotikDeviceId' => (string) $package->mikrotik_device_id,
                    'name' => $package->name,
                    'price' => (float) $package->price,
                    'profile' => $package->mikrotik_profile,
                ])->values()
            ) }};
            const initialPackageId = @json((string) old('internet_package_id', ''));

            if (!mikrotikSelect || !packageSelect || !profileInput) {
                return;
            }

            let profileEdited = profileInput.value.trim() !== '';

            const applyPackageProfile = () => {
                const selectedOption = packageSelect.options[packageSelect.selectedIndex];
                const packageProfile = selectedOption?.dataset?.mikrotikProfile || '';

                if (!profileEdited && packageProfile) {
                    profileInput.value = packageProfile;
                }
            };

            const renderPackages = (selectedPackageId = '') => {
                const selectedDeviceId = mikrotikSelect.value;
                const matchingPackages = packages.filter(
                    (item) => item.mikrotikDeviceId === selectedDeviceId
                );

                packageSelect.innerHTML = '';
                packageSelect.add(new Option(
                    selectedDeviceId
                        ? 'Pilih Paket Internet'
                        : 'Pilih MikroTik terlebih dahulu',
                    ''
                ));

                matchingPackages.forEach((item) => {
                    const price = new Intl.NumberFormat('id-ID').format(item.price);
                    const option = new Option(
                        `${item.name} - Rp ${price}`,
                        item.id
                    );
                    option.dataset.mikrotikProfile = item.profile || '';
                    packageSelect.add(option);
                });

                if (
                    selectedPackageId
                    && matchingPackages.some((item) => item.id === selectedPackageId)
                ) {
                    packageSelect.value = selectedPackageId;
                } else if (matchingPackages.length === 1) {
                    packageSelect.value = matchingPackages[0].id;
                }

                packageSelect.disabled = !selectedDeviceId || matchingPackages.length === 0;

                if (emptyMessage) {
                    emptyMessage.hidden = !selectedDeviceId || matchingPackages.length > 0;
                }

                if (!profileEdited) {
                    profileInput.value = '';
                }

                applyPackageProfile();
            };

            mikrotikSelect.addEventListener('change', () => {
                renderPackages();
            });
            packageSelect.addEventListener('change', applyPackageProfile);
            profileInput.addEventListener('input', () => {
                profileEdited = true;
            });
            renderPackages(initialPackageId);
        });

        document.addEventListener('DOMContentLoaded', async function () {
            const province = document.getElementById('address_province');
            const regency = document.getElementById('address_regency');
            const district = document.getElementById('address_district');
            const village = document.getElementById('address_village');
            const status = document.querySelector('[data-region-status]');
            const form = document.querySelector('form[action="{{ route('customers.store') }}"]');
            if (!province || !regency || !district || !village || !form) return;

            const apiBase = 'https://www.emsifa.com/api-wilayah-indonesia/api';
            const fallback = {
                provinces: [{ id: '33', name: 'Jawa Tengah' }],
                regencies: [{ id: '3325', name: 'Kabupaten Batang' }],
                districts: [{ id: '332506', name: 'Kecamatan Banyuputih' }],
                villages: [
                    { id: '3325062001', name: 'Desa Banyuputih' },
                    { id: '3325062002', name: 'Desa Sembung' },
                    { id: '3325062003', name: 'Desa Bulu' },
                ],
            };

            const setOptions = (select, items, placeholder) => {
                select.innerHTML = `<option value="">${placeholder}</option>`;
                items.forEach((item) => {
                    const option = new Option(item.name, item.id);
                    option.dataset.name = item.name;
                    select.add(option);
                });
                select.disabled = items.length === 0;
            };

            const load = async (path, fallbackItems) => {
                try {
                    const response = await fetch(`${apiBase}/${path}`, { headers: { Accept: 'application/json' } });
                    if (!response.ok) throw new Error('Wilayah tidak tersedia');
                    return await response.json();
                } catch (error) {
                    return fallbackItems;
                }
            };

            const chooseByName = (select, keyword) => {
                const option = [...select.options].find((item) => item.text.toLowerCase().includes(keyword.toLowerCase()));
                if (option) select.value = option.value;
                return option;
            };

            const loadRegencies = async () => {
                const items = await load(`regencies/${province.value}.json`, fallback.regencies);
                setOptions(regency, items, 'Pilih kabupaten/kota');
                if (chooseByName(regency, 'Batang')) await loadDistricts();
            };
            const loadDistricts = async () => {
                const items = await load(`districts/${regency.value}.json`, fallback.districts);
                setOptions(district, items, 'Pilih kecamatan');
                if (chooseByName(district, 'Banyuputih')) await loadVillages();
            };
            const loadVillages = async () => {
                const items = await load(`villages/${district.value}.json`, fallback.villages);
                setOptions(village, items, 'Pilih desa/kelurahan');
                chooseByName(village, 'Banyuputih');
                window.dispatchEvent(new CustomEvent('customer-region-changed'));
            };

            setOptions(province, await load('provinces.json', fallback.provinces), 'Pilih provinsi');
            chooseByName(province, 'Jawa Tengah');
            province.addEventListener('change', loadRegencies);
            regency.addEventListener('change', loadDistricts);
            district.addEventListener('change', loadVillages);
            village.addEventListener('change', () => window.dispatchEvent(new CustomEvent('customer-region-changed')));
            await loadRegencies();
            if (status) status.textContent = 'Pilih wilayah secara bertingkat. Data wilayah dimuat dinamis.';

            form.addEventListener('submit', function () {
                const address = document.getElementById('address');
                const region = [village, district, regency, province]
                    .map((select) => select.options[select.selectedIndex]?.dataset?.name || '')
                    .filter(Boolean)
                    .join(', ');
                const detail = address?.value.trim() || '';
                if (address && region && !detail.startsWith(region)) {
                    address.value = detail ? `${region} — ${detail}` : region;
                }
            });
        });

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
            // Nonaktifkan zoom dengan roda mouse supaya scroll form tidak "bablas".
            // Pengguna tetap bisa zoom memakai tombol +/- atau gesture pinch.
            const map = L.map(mapElement, {
                scrollWheelZoom: false,
                zoomControl: true,
                doubleClickZoom: true,
                dragging: true,
            }).setView(initialLocation, hasInitialLocation ? 16 : 15);
            let marker = null;
            let manualLocationSelected = hasInitialLocation;

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

            map.on('click', (event) => {
                manualLocationSelected = true;
                setLocation(event.latlng.lat, event.latlng.lng);
            });

            window.addEventListener('customer-region-changed', async function () {
                if (manualLocationSelected) return;
                const labels = ['address_village', 'address_district', 'address_regency', 'address_province']
                    .map((id) => document.getElementById(id)?.selectedOptions?.[0]?.text)
                    .filter(Boolean)
                    .join(', ');
                if (!labels) return;
                try {
                    const response = await fetch(`https://nominatim.openstreetmap.org/search?format=jsonv2&limit=1&q=${encodeURIComponent(labels)}`);
                    const result = (await response.json())[0];
                    if (result) map.setView([Number(result.lat), Number(result.lon)], 14);
                } catch (error) {
                    map.setView(defaultLocation, 14);
                }
            });

            document.getElementById('use-current-location')?.addEventListener('click', function () {
                if (!navigator.geolocation) {
                    alert('Browser tidak mendukung pembacaan lokasi.');
                    return;
                }

                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        manualLocationSelected = true;
                        setLocation(position.coords.latitude, position.coords.longitude);
                    },
                    () => alert('Lokasi tidak dapat dibaca. Pastikan izin lokasi browser sudah diberikan.'),
                    { enableHighAccuracy: true, timeout: 10000 }
                );
            });
        });
    </script>
</x-app-layout>