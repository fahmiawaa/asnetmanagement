<x-app-layout>
    @php
        $mappedTotal = (int) $mappedCount;
        $unmappedTotal = (int) $unmappedCount;
        $allCustomersTotal = max($mappedTotal + $unmappedTotal, 1);
        $mappedPercentage = round(($mappedTotal / $allCustomersTotal) * 100);
        $unmappedPercentage = 100 - $mappedPercentage;
        $selectedMapCenter = $areaCenters[$area] ?? [-6.950, 109.935];
    @endphp

    <div class="map-page">
        <div class="map-page-heading">
            <div>
                <h1>Map Pelanggan</h1>
            </div>
        </div>

        <form method="GET" action="{{ route('map.index') }}" class="map-reference-filters">
            <label class="map-filter-search">
                <img src="{{ asset('images/icons/search.svg') }}" alt="">
                <input type="search" name="q" value="{{ $search }}" placeholder="Cari Pelanggan..." aria-label="Cari pelanggan">
            </label>
            <label class="map-filter-select">
                <select aria-label="Tipe pelanggan">
                    <option>Tipe Pelanggan</option>
                    <option>Semua Pelanggan</option>
                </select>
            </label>
            <label class="map-filter-select">
                <select aria-label="Status tagihan">
                    <option>Status Tagihan</option>
                    <option>Aktif</option>
                    <option>Menunggak</option>
                    <option>Terisolir</option>
                </select>
            </label>
            <label class="map-filter-select">
                <select name="area" aria-label="Alamat">
                    <option value="">Semua Alamat</option>
                    @foreach ($areaOptions as $areaOption)
                        <option value="{{ $areaOption }}" @selected($area === $areaOption)>{{ $areaOption }}</option>
                    @endforeach
                </select>
            </label>
            <button type="button" class="map-period-control">
                <img src="{{ asset('images/icons/calendar.svg') }}" alt="">
                Periode
            </button>
            <button type="submit" class="map-filter-button">
                <img src="{{ asset('images/icons/filter.svg') }}" alt="">
                Filter
            </button>
        </form>

        <div class="map-reference-layout">
            <section class="map-reference-card">
                <div class="map-card-toolbar">
                    <form method="GET" action="{{ route('map.index') }}" class="map-area-selector-form">
                        <input type="hidden" name="q" value="{{ $search }}">
                        <select name="area" class="map-area-selector" aria-label="Pilih alamat map" onchange="this.form.submit()">
                            <option value="">Semua Alamat</option>
                            @foreach ($areaOptions as $areaOption)
                                <option value="{{ $areaOption }}" @selected($area === $areaOption)>{{ $areaOption }}</option>
                            @endforeach
                        </select>
                    </form>
                    <div class="map-card-tools">
                        <button type="button" aria-label="Lokasi saya">⌾</button>
                        <button type="button" aria-label="Layer peta">▱</button>
                    </div>
                </div>

                <div id="customer-map" class="customer-map customer-map-reference" aria-label="Peta lokasi pelanggan ASNet"></div>
                @if ($mappedTotal === 0)
                    <p class="map-no-markers-notice">Belum ada marker pelanggan pada wilayah ini. Peta tetap dapat digeser dan dizoom.</p>
                @endif

                <div class="map-reference-legend">
                    <span><i class="legend-dot legend-active"></i>Aktif</span>
                    <span><i class="legend-dot legend-overdue"></i>Tertunda</span>
                    <span><i class="legend-dot legend-inactive"></i>Non-Aktif</span>
                </div>
            </section>

            <aside class="map-statistics-card">
                <h2>Statistik Peta</h2>
                <div class="map-stat-main">
                    <span class="map-stat-icon"><img src="{{ asset('images/icons/map.svg') }}" alt=""></span>
                    <div><strong>Pelanggan Dipetakan</strong><b>{{ number_format($mappedTotal) }}</b></div>
                    <em>{{ $mappedPercentage }}%</em>
                </div>
                <div class="map-progress"><span style="width: {{ $mappedPercentage }}%"></span></div>
                <div class="map-stat-main map-stat-unmapped">
                    <span class="map-stat-icon"><img src="{{ asset('images/icons/map.svg') }}" alt=""></span>
                    <div><strong>Pelanggan Belum Dipetakan</strong><b>{{ number_format($unmappedTotal) }}</b></div>
                    <em>{{ $unmappedPercentage }}%</em>
                </div>
                <div class="map-progress map-progress-warning"><span style="width: {{ $unmappedPercentage }}%"></span></div>
                <div class="map-status-list">
                    <div><i class="legend-dot legend-active"></i><span>Aktif</span><b>{{ number_format((int) ($statusCounts->get('active', 0))) }}</b></div>
                    <div><i class="legend-dot legend-overdue"></i><span>Tertunda</span><b>{{ number_format((int) ($statusCounts->get('overdue', 0))) }}</b></div>
                    <div><i class="legend-dot legend-inactive"></i><span>Non-Aktif</span><b>{{ number_format((int) ($statusCounts->get('inactive', 0))) }}</b></div>
                </div>
                <a href="{{ route('customers.index', ['status' => 'unmapped']) }}" class="map-sync-button">
                    <span>⟳</span> Sinkronisasi Data
                </a>
            </aside>
        </div>

        <div class="map-reference-footer">© 2024 ASNet Billing Management.<br>Semua hak dilindungi. Versi 2.1.0</div>
    </div>

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
        <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" crossorigin="">
        <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" crossorigin="">
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
        <script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js" crossorigin=""></script>
    <script>
            document.addEventListener('DOMContentLoaded', function () {
                const customers = @json($customers);
                const mapElement = document.getElementById('customer-map');
                if (!mapElement || !window.L || !window.L.markerClusterGroup) return;

                // Jangan biarkan scroll halaman berubah menjadi zoom peta.
                // Zoom tetap tersedia melalui tombol +/- dan gesture pinch.
                const map = L.map(mapElement, {
                    scrollWheelZoom: false,
                    zoomControl: true,
                    doubleClickZoom: true,
                    dragging: true,
                }).setView(@json($selectedMapCenter), 14);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; OpenStreetMap contributors',
                }).addTo(map);

                const markers = L.markerClusterGroup({ showCoverageOnHover: false, maxClusterRadius: 42 });
                const escapeHtml = (value) => String(value ?? '')
                    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
                const statusClass = { active: 'active', overdue: 'overdue', isolated: 'inactive', inactive: 'inactive' };

                customers.forEach((customer) => {
                    const markerIcon = L.divIcon({
                        className: 'asnet-map-marker-wrap',
                        html: `<span class="asnet-map-marker ${statusClass[customer.status] || 'active'}"></span>`,
                        iconSize: [26, 26],
                        iconAnchor: [13, 13],
                    });
                    const marker = L.marker([customer.latitude, customer.longitude], { icon: markerIcon });
                    marker.bindPopup(`
                        <div class="customer-map-popup reference-popup">
                            <strong>Detail Pelanggan</strong>
                            <div><span>Nama</span><b>${escapeHtml(customer.name)}</b></div>
                            <div><span>ID Pelanggan</span><b>${escapeHtml(customer.code)}</b></div>
                            <div><span>Paket</span><b>${escapeHtml(customer.package || '-')}</b></div>
                            <div><span>Status</span><b class="popup-status ${statusClass[customer.status] || 'active'}">● ${escapeHtml(customer.status)}</b></div>
                            <div><span>Alamat</span><b>${escapeHtml(customer.address)}</b></div>
                            <a href="${escapeHtml(customer.url)}">Buka detail <span>›</span></a>
                        </div>
                    `);
                    markers.addLayer(marker);
                });

                map.addLayer(markers);
                if (markers.getBounds().isValid()) map.fitBounds(markers.getBounds().pad(0.18));
            });
    </script>
</x-app-layout>
