<?php if (isset($component)) { $__componentOriginal9ac128a9029c0e4701924bd2d73d7f54 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54 = $attributes; } ?>
<?php $component = App\View\Components\AppLayout::resolve([] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('app-layout'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\AppLayout::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
    <?php
        $typeLabels = [
            'server' => 'Server', 'mikrotik' => 'MikroTik', 'olt' => 'OLT',
            'pon_port' => 'PON Port', 'odc' => 'ODC', 'odp' => 'ODP', 'ont' => 'ONT',
        ];
        $stats = $graph['stats'];
    ?>

    <div class="topology-page">
        <div class="reference-page-heading">
            <div>
                <h1>Topologi PON</h1>
            </div>
        </div>

        <?php if($errors->any()): ?>
            <div class="ont-banner ont-banner-error" role="alert">
                <ul>
                    <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <li><?php echo e($error); ?></li>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="ont-stats-grid topo-stats">
            <div class="ont-stat-card"><span>Node</span><strong><?php echo e($stats['nodes']); ?></strong></div>
            <div class="ont-stat-card"><span>Link</span><strong><?php echo e($stats['links']); ?></strong></div>
            <div class="ont-stat-card"><span>Node ONT</span><strong><?php echo e($stats['ont_nodes']); ?></strong></div>
            <div class="ont-stat-card"><span>Belum Terhubung</span><strong><?php echo e($stats['orphans']); ?></strong><small>node tanpa parent</small></div>
        </div>

        <div class="topo-layout">
            <section class="detail-reference-card topo-map-card">
                <div class="topo-legend" aria-label="Legenda">
                    <span><i class="topo-swatch" style="background:#16a34a"></i>Baik</span>
                    <span><i class="topo-swatch" style="background:#2563eb"></i>Overload</span>
                    <span><i class="topo-swatch" style="background:#d98916"></i>Warning</span>
                    <span><i class="topo-swatch" style="background:#d45263"></i>Kritis</span>
                    <span><i class="topo-swatch" style="background:#8a94a6"></i>Unknown</span>
                    <span class="topo-legend-note">Garis beranimasi = online · animasi lambat = warning · putus-putus redup = offline</span>
                </div>
                <div class="topo-toolbar">
                    <?php if($canManage): ?>
                        <button type="button" class="topo-tool-btn" id="btn-add-node">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
                            Tambah Item
                        </button>
                        <button type="button" class="topo-tool-btn" id="btn-add-link">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                            Tambah Kabel
                        </button>
                    <?php endif; ?>
                </div>
                <div id="topology-map" class="topology-map"></div>
                <p id="topology-map-hint" class="ont-muted" hidden></p>
            </section>

            <aside class="topo-side">
                <section class="detail-reference-card" id="topo-empty-panel">
                    <h2>Detail</h2>
                    <p class="ont-muted">Pilih node dari peta untuk melihat detail<?php echo e($canManage ? ' dan mengubahnya' : ''); ?>.</p>
                </section>

                <section class="detail-reference-card topo-detail-card" id="topo-node-panel" hidden>
                    <div class="detail-card-heading">
                        <h2 id="node-title">Node</h2>
                        <span class="status-badge" data-field="status"></span>
                    </div>

                    <h3 class="ont-subheading">Identitas Node</h3>
                    <dl class="ont-dl">
                        <div><dt>Tipe</dt><dd data-field="type"></dd></div>
                        <div><dt>Kode</dt><dd data-field="code"></dd></div>
                        <div><dt>Teknologi</dt><dd data-field="technology"></dd></div>
                        <div><dt>Urutan</dt><dd data-field="sort"></dd></div>
                    </dl>

                    <h3 class="ont-subheading">Lokasi</h3>
                    <dl class="ont-dl">
                        <div><dt>Latitude</dt><dd data-field="lat"></dd></div>
                        <div><dt>Longitude</dt><dd data-field="lng"></dd></div>
                        <div><dt>Sumber Koordinat</dt><dd data-field="coord-source"></dd></div>
                        <div><dt>Parent</dt><dd data-field="parent"></dd></div>
                    </dl>

                    <h3 class="ont-subheading">Ringkasan ONT</h3>
                    <dl class="ont-dl">
                        <div><dt>Total ONT</dt><dd data-field="ont-total"></dd></div>
                        <div><dt>Online</dt><dd data-field="ont-online"></dd></div>
                        <div><dt>Warning</dt><dd data-field="ont-warning"></dd></div>
                        <div><dt>Offline</dt><dd data-field="ont-offline"></dd></div>
                    </dl>

                    <div id="node-ont-block" hidden>
                        <h3 class="ont-subheading">Perangkat ONT</h3>
                        <dl class="ont-dl">
                            <div><dt>Serial</dt><dd data-field="ont-serial"></dd></div>
                            <div><dt>Pelanggan</dt><dd data-field="ont-customer"></dd></div>
                            <div><dt>RX Power</dt><dd data-field="ont-rx"></dd></div>
                            <div><dt>Status ACS</dt><dd data-field="ont-status"></dd></div>
                            <div><dt>Last Inform</dt><dd data-field="ont-inform"></dd></div>
                        </dl>
                        <a id="node-ont-link" href="#" class="table-view-button">Buka detail ONT</a>
                    </div>

                    <h3 class="ont-subheading">Port</h3>
                    <ul id="node-ports" class="topo-ports"></ul>

                    <button type="button" id="node-edit-link" class="customer-filter-button" hidden>Edit kabel masuk</button>

                    <?php if($canManage): ?>
                        <h3 class="ont-subheading">Ubah node</h3>
                        <form method="POST" id="form-node-edit" class="topo-form">
                            <?php echo csrf_field(); ?>
                            <?php echo method_field('PATCH'); ?>
                            <label>Nama<input type="text" name="name" id="edit-name" required maxlength="255"></label>
                            <label>Kode<input type="text" name="code" id="edit-code" maxlength="50"></label>
                            <label>Teknologi
                                <select name="technology" id="edit-technology">
                                    <?php $__currentLoopData = $technologies; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $technology): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <option value="<?php echo e($technology); ?>"><?php echo e(strtoupper($technology)); ?></option>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </select>
                            </label>
                            <label>Parent<select name="parent_id" id="edit-parent"></select></label>
                            <div class="topo-row">
                                <label>Latitude<input type="number" name="latitude" id="edit-lat" step="any" min="-90" max="90"></label>
                                <label>Longitude<input type="number" name="longitude" id="edit-lng" step="any" min="-180" max="180"></label>
                            </div>
                            <button type="button" class="topo-link-button" data-pick="edit">Ambil koordinat dari peta</button>
                            <label>Urutan<input type="number" name="sort_order" id="edit-sort" min="0" max="100000"></label>
                            <label class="topo-check"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" id="edit-active"> Aktif</label>
                            <button type="submit" class="customer-filter-button">Simpan Node</button>
                        </form>

                        <h3 class="ont-subheading">Tambah port</h3>
                        <form method="POST" id="form-port" class="topo-form">
                            <?php echo csrf_field(); ?>
                            <div class="topo-row">
                                <label>Nomor<input type="number" name="port_number" min="1" max="999" required></label>
                                <label>Tipe
                                    <select name="port_type">
                                        <?php $__currentLoopData = $portTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $portType): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <option value="<?php echo e($portType); ?>"><?php echo e($portType); ?></option>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                    </select>
                                </label>
                                <label>Kapasitas<input type="number" name="capacity" min="1" max="1024"></label>
                            </div>
                            <button type="submit" class="customer-filter-button">Tambah Port</button>
                        </form>
                    <?php endif; ?>
                </section>

                <section class="detail-reference-card topo-detail-card" id="topo-link-panel" hidden>
                    <div class="detail-card-heading">
                        <h2>Kabel</h2>
                        <span class="status-badge" data-field="link-status"></span>
                    </div>

                    <h3 class="ont-subheading">Identitas Kabel</h3>
                    <dl class="ont-dl">
                        <div><dt>Jenis Kabel</dt><dd data-field="link-type"></dd></div>
                        <div><dt>Dari</dt><dd data-field="link-from"></dd></div>
                        <div><dt>Ke</dt><dd data-field="link-to"></dd></div>
                        <div><dt>Port Asal</dt><dd data-field="link-from-port"></dd></div>
                        <div><dt>Port Tujuan</dt><dd data-field="link-to-port"></dd></div>
                    </dl>

                    <h3 class="ont-subheading">Pengukuran</h3>
                    <dl class="ont-dl">
                        <div><dt>Panjang Kabel (otomatis)</dt><dd data-field="link-recorded-length"></dd></div>
                        <div><dt>Redaman Ukur</dt><dd data-field="link-measured-text"></dd></div>
                        <div><dt>Redaman Hitung</dt><dd data-field="link-calculated-text"></dd></div>
                    </dl>
                    <p class="ont-muted">Panjang kabel dihitung otomatis dari skala peta dan mengikuti posisi node maupun waypoint terbaru.</p>

                    <h3 class="ont-subheading">Kualitas &amp; Pelanggan</h3>
                    <dl class="ont-dl">
                        <div><dt>Kualitas RX</dt><dd data-field="link-quality"></dd></div>
                        <div><dt>Status ACS</dt><dd data-field="link-acs"></dd></div>
                        <div><dt>ONT Hilir</dt><dd data-field="link-summary"></dd></div>
                    </dl>

                    <?php if($canManage): ?>
                        <form method="POST" id="form-link" class="topo-form">
                            <?php echo csrf_field(); ?>
                            <?php echo method_field('PATCH'); ?>
                            <h3 class="ont-subheading">Ubah Kabel</h3>
                            <label>Jenis Kabel
                                <select name="cable_type" id="link-type-select">
                                    <option value="">— belum ditentukan —</option>
                                    <?php $__currentLoopData = $cableTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cableKey => $cableLabel): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <option value="<?php echo e($cableKey); ?>"><?php echo e($cableLabel); ?></option>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </select>
                            </label>
                            <div class="topo-row">
                                <label>Port asal<select name="from_port_id" id="link-from-port"></select></label>
                                <label>Port tujuan<select name="to_port_id" id="link-to-port"></select></label>
                            </div>
                            <div class="topo-row">
                                <label>Status
                                    <select name="status" id="link-status">
                                        <?php $__currentLoopData = $linkStatuses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $linkStatus): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <option value="<?php echo e($linkStatus); ?>"><?php echo e($linkStatus); ?></option>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                    </select>
                                </label>
                            </div>
                            <div class="topo-row">
                                <label>Redaman ukur (dB)<input type="number" name="measured_attenuation_db" id="link-measured" step="any" min="0" max="100"></label>
                                <label>Redaman hitung (dB)<input type="number" name="calculated_attenuation_db" id="link-calculated" step="any" min="0" max="100"></label>
                            </div>
                            <label>Waypoint (JSON [[lat,lng],...])
                                <textarea name="path_coordinates" id="link-path" rows="4" spellcheck="false"></textarea>
                            </label>
                            <div class="topo-row">
                                <button type="button" class="topo-link-button" id="waypoint-toggle">Tambah waypoint dari peta</button>
                                <button type="button" class="topo-link-button" id="waypoint-undo">Hapus titik terakhir</button>
                                <button type="button" class="topo-link-button" id="waypoint-clear">Kosongkan</button>
                            </div>
                            <button type="submit" class="customer-filter-button">Simpan Kabel</button>
                        </form>
                    <?php else: ?>
                        <dl class="ont-dl">
                            <div><dt>Jumlah waypoint</dt><dd data-field="link-waypoints"></dd></div>
                        </dl>
                    <?php endif; ?>
                </section>

                <?php if($canManage): ?>
                    <section class="detail-reference-card" id="topo-create-panel" hidden>
                        <h2>Tambah Node</h2>
                        <form method="POST" action="<?php echo e(route('network.nodes.store')); ?>" class="topo-form" id="form-node-create">
                            <?php echo csrf_field(); ?>
                            <label>Tipe
                                <select name="type" id="create-type" required>
                                    <?php $__currentLoopData = $nodeTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $nodeType): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <option value="<?php echo e($nodeType); ?>"><?php echo e($typeLabels[$nodeType]); ?></option>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </select>
                            </label>
                            <label>Parent<select name="parent_id" id="create-parent"></select></label>
                            <label id="create-ont-wrap" hidden>Perangkat ONT
                                <select name="ont_device_id" id="create-ont">
                                    <option value="">Pilih ONT...</option>
                                    <?php $__currentLoopData = $ontOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $option): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <option value="<?php echo e($option['id']); ?>"><?php echo e($option['label']); ?></option>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </select>
                            </label>
                            <label>Nama<input type="text" name="name" id="create-name" required maxlength="255"></label>
                            <label>Kode<input type="text" name="code" maxlength="50"></label>
                            <label>Teknologi
                                <select name="technology">
                                    <?php $__currentLoopData = $technologies; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $technology): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <option value="<?php echo e($technology); ?>" <?php if($technology === 'unknown'): echo 'selected'; endif; ?>><?php echo e(strtoupper($technology)); ?></option>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </select>
                            </label>
                            <div class="topo-row">
                                <label>Latitude<input type="number" name="latitude" id="create-lat" step="any" min="-90" max="90"></label>
                                <label>Longitude<input type="number" name="longitude" id="create-lng" step="any" min="-180" max="180"></label>
                            </div>
                            <button type="button" class="topo-link-button" data-pick="create">Ambil koordinat dari peta</button>
                            <button type="submit" class="customer-filter-button">Tambah Node</button>
                        </form>
                    </section>

                    <section class="detail-reference-card topo-detail-card" id="topo-cable-panel" hidden>
                        <div class="detail-card-heading"><h2>Tambah Kabel</h2></div>

                        <form method="POST" action="<?php echo e(route('network.links.store')); ?>" class="topo-form" id="form-cable-create">
                            <?php echo csrf_field(); ?>

                            <h3 class="ont-subheading">1. Pilih Jenis Kabel</h3>
                            <label>Jenis Kabel
                                <select name="cable_type" id="cable-type" required>
                                    <option value="">— pilih jenis kabel —</option>
                                    <?php $__currentLoopData = $cableTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cableKey => $cableLabel): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <option value="<?php echo e($cableKey); ?>"><?php echo e($cableLabel); ?></option>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </select>
                            </label>

                            <h3 class="ont-subheading">2. Tentukan Titik Kabel</h3>
                            <p class="ont-muted">Klik pada peta untuk menambahkan titik jalur kabel, atau klik node untuk menyambungkan ke node tersebut.</p>
                            <div class="topo-row">
                                <label>Node asal<select name="from_node_id" id="cable-from-node"><option value="">— Bebas —</option></select></label>
                                <label>Node tujuan<select name="to_node_id" id="cable-to-node"><option value="">— Bebas —</option></select></label>
                            </div>
                            <div class="topo-row">
                                <label>Port asal<select name="from_port_id" id="cable-from-port"></select></label>
                                <label>Port tujuan<select name="to_port_id" id="cable-to-port"></select></label>
                            </div>
                            <div class="topo-row">
                                <button type="button" class="topo-link-button" id="cable-waypoint-undo">Hapus titik terakhir</button>
                                <button type="button" class="topo-link-button" id="cable-waypoint-clear">Kosongkan</button>
                            </div>
                            <label>Waypoint (JSON [[lat,lng],...])
                                <textarea name="path_coordinates" id="cable-path" rows="3" spellcheck="false"></textarea>
                            </label>

                            <h3 class="ont-subheading">3. Panjang Kabel (otomatis)</h3>
                            <dl class="ont-dl">
                                <div><dt>Perkiraan panjang dari peta</dt><dd data-field="cable-map-length">-</dd></div>
                            </dl>
                            <p class="ont-muted">Tidak ada kolom isian panjang. Nilai dihitung dari skala peta dan disimpan otomatis.</p>

                            <label>Status
                                <select name="status" id="cable-status">
                                    <?php $__currentLoopData = $linkStatuses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $linkStatus): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <option value="<?php echo e($linkStatus); ?>"><?php echo e($linkStatus); ?></option>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </select>
                            </label>

                            <button type="submit" class="customer-filter-button">Simpan Kabel</button>
                        </form>
                    </section>
                <?php endif; ?>
            </aside>
        </div>
    </div>

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (!window.L) { return; }

            const graph = <?php echo json_encode($graph, 15, 512) ?>;
            const canManage = <?php echo json_encode($canManage, 15, 512) ?>;
            const focusId = <?php echo json_encode($focusId, 15, 512) ?>;
            const routes = {
                nodeUpdate: <?php echo json_encode(route('network.nodes.update', ['node' => '__ID__']), 512) ?>,
                portStore: <?php echo json_encode(route('network.nodes.ports.store', ['node' => '__ID__']), 512) ?>,
                linkUpdate: <?php echo json_encode(route('network.links.update', ['link' => '__ID__']), 512) ?>,
                linkStore: <?php echo json_encode(route('network.links.store'), 15, 512) ?>,
            };
            const TYPE_LABEL = { server: 'Server', mikrotik: 'MikroTik', olt: 'OLT', pon_port: 'PON Port', odc: 'ODC', odp: 'ODP', ont: 'ONT' };
            const CABLE_TYPE_LABEL = <?php echo json_encode($cableTypes, 15, 512) ?>;
            const TYPE_COLOR = { server: '#1e40af', mikrotik: '#7c3aed', olt: '#0d9488', pon_port: '#0284c7', odc: '#b45309', odp: '#c2410c', ont: '#2563eb' };
            const QUALITY_COLOR = { good: '#16a34a', overload: '#2563eb', warning: '#d98916', critical: '#d45263', unknown: '#8a94a6' };
            const QUALITY_LABEL = { good: 'Baik', overload: 'Overload', warning: 'Warning', critical: 'Kritis', unknown: 'Unknown' };
            const STATUS_LABEL = { online: 'Online', warning: 'Warning', offline: 'Offline', unknown: 'Unknown', missing: 'Missing' };

            const TYPE_ICONS = {
                server: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><rect x="2" y="2" width="20" height="8" rx="2"/><rect x="2" y="14" width="20" height="8" rx="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/></svg>',
                mikrotik: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M4 17l6-6-6-6"/><path d="M12 19h8"/></svg>',
                olt: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>',
                pon_port: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="4"/></svg>',
                odc: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>',
                odp: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>',
                ont: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>',
            };

            const nodesById = new Map(graph.nodes.map(function (n) { return [n.id, n]; }));
            const linksById = new Map(graph.links.map(function (l) { return [l.id, l]; }));
            const linkByTo = new Map(graph.links.map(function (l) { return [l.to_node_id, l]; }));
            const $ = function (id) { return document.getElementById(id); };

            function el(tag, text, className) {
                const node = document.createElement(tag);
                if (text !== null && text !== undefined) { node.textContent = text; }
                if (className) { node.className = className; }
                return node;
            }
            function coords(node) {
                if (!node || node.latitude === null || node.longitude === null) { return null; }
                return [node.latitude, node.longitude];
            }
            function fmtRx(ont) {
                return ont.rx_power_dbm === null ? 'Tidak valid' : ont.rx_power_dbm.toFixed(2) + ' dBm (' + (QUALITY_LABEL[ont.quality] || ont.quality) + ')';
            }
            function summaryText(s) {
                if (!s.ont_total) { return 'Tidak ada ONT di bawah node ini'; }
                return s.ont_total + ' ONT: ' + s.online + ' online, ' + s.warning + ' warning, ' + s.offline + ' offline, ' + s.unknown + ' unknown';
            }
            function setField(root, name, text) {
                const target = root.querySelector('[data-field="' + name + '"]');
                if (target) { target.textContent = text; }
            }
            function setBadge(root, name, text, className) {
                const target = root.querySelector('[data-field="' + name + '"]');
                if (!target) { return; }
                target.textContent = text;
                target.className = 'status-badge ' + className;
            }
            function linkPathPoints(link, from, to) {
                const path = link.path || [];
                const points = path.slice();
                const a = from ? coords(from) : (points.length ? points[0] : null);
                const b = to ? coords(to) : (points.length ? points[points.length - 1] : null);
                if (!a || !b) { return null; }
                if (from) { points.unshift(a); }
                if (to) { points.push(b); }
                return points.length >= 2 ? points : null;
            }
            function measureMeters(points) {
                if (!points || points.length < 2) { return null; }
                let total = 0;
                for (let i = 1; i < points.length; i++) {
                    total += map.distance(points[i - 1], points[i]);
                }
                return total;
            }
            function fmtMeters(value) {
                if (value === null || value === undefined || isNaN(value)) { return '-'; }
                if (value < 1000) { return Math.round(value) + ' m'; }
                return (value / 1000).toFixed(2).replace('.', ',') + ' km';
            }
            function portText(node, portId) {
                if (!node || !portId) { return '-'; }
                const port = (node.ports || []).find(function (p) { return p.id === portId; });
                return port ? port.port_type + ' ' + port.port_number : '#' + portId;
            }

            const map = L.map('topology-map', { zoomControl: true }).setView([-6.95, 109.935], 13);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors',
            }).addTo(map);
            const cableLayer = L.layerGroup().addTo(map);
            const nodeLayer = L.layerGroup().addTo(map);
            const previewLayer = L.layerGroup().addTo(map);
            const markers = new Map();
            const cableLines = new Map();

            function nodeTooltip(node) {
                const box = el('div');
                box.append(el('strong', (TYPE_LABEL[node.type] || node.type) + ' · ' + node.name));
                if (node.ont) {
                    box.append(el('div', 'Pelanggan: ' + (node.ont.customer_name || 'belum dipetakan')));
                    box.append(el('div', 'RX: ' + fmtRx(node.ont)));
                    box.append(el('div', 'Status: ' + (STATUS_LABEL[node.ont.status] || node.ont.status)));
                    box.append(el('div', 'Last inform: ' + (node.ont.last_inform_human || '-')));
                } else if (node.summary.ont_total > 0) {
                    box.append(el('div', summaryText(node.summary)));
                }
                return box;
            }
            function linkTooltip(link, from, to) {
                const box = el('div');
                const fromName = from ? from.name : 'Bebas';
                const toName = to ? to.name : 'Bebas';
                box.append(el('strong', fromName + ' → ' + toName));
                box.append(el('div', 'RX terburuk: ' + (QUALITY_LABEL[link.quality] || link.quality)));
                box.append(el('div', 'Status ACS: ' + (STATUS_LABEL[link.acs_status] || link.acs_status)));
                box.append(el('div', summaryText(link.summary)));
                if (to && to.ont) {
                    box.append(el('div', 'Pelanggan: ' + (to.ont.customer_name || 'belum dipetakan')));
                    box.append(el('div', 'Last inform: ' + (to.ont.last_inform_human || '-')));
                }
                return box;
            }

            const bounds = [];

            graph.links.forEach(function (link) {
                const from = nodesById.get(link.from_node_id);
                const to = nodesById.get(link.to_node_id);
                const a = from ? coords(from) : (link.path && link.path.length ? link.path[0] : null);
                const b = to ? coords(to) : (link.path && link.path.length ? link.path[link.path.length - 1] : null);
                if (!a || !b) { return; }
                const points = [a].concat(link.path, [b]);
                const status = link.acs_status;
                const color = QUALITY_COLOR[link.quality] || QUALITY_COLOR.unknown;
                const base = L.polyline(points, {
                    color: color,
                    weight: 4,
                    opacity: status === 'offline' ? 0.45 : 0.9,
                    dashArray: status === 'offline' ? '6 8' : null,
                    className: 'cable cable-' + status + (link.status === 'inactive' ? ' cable-inactive' : ''),
                }).addTo(cableLayer);
                base.bindTooltip(linkTooltip(link, from, to), { sticky: true });
                base.on('click', function () { selectLink(link.id); });
                cableLines.set(link.id, { base: base, link: link, from: from, to: to });
                if (status === 'online' || status === 'warning') {
                    L.polyline(points, {
                        color: '#ffffff',
                        weight: 2,
                        opacity: 0.85,
                        dashArray: '2 12',
                        interactive: false,
                        className: 'cable-flow cable-flow-' + status,
                    }).addTo(cableLayer);
                }
            });

            graph.nodes.forEach(function (node) {
                const position = coords(node);
                if (!position) { return; }
                bounds.push(position);
                const color = TYPE_COLOR[node.type] || '#475569';
                const iconHtml = '<div class="topo-node-icon" style="background:' + color + '">' + (TYPE_ICONS[node.type] || TYPE_ICONS.ont) + '</div>';
                const icon = L.divIcon({
                    className: 'topo-div-icon',
                    html: iconHtml,
                    iconSize: [32, 32],
                    iconAnchor: [16, 16],
                });
                const marker = L.marker(position, { icon: icon, draggable: canManage }).addTo(nodeLayer);
                marker.bindTooltip(nodeTooltip(node), { direction: 'top' });
                marker.on('click', function () {
                    if (cableMode) {
                        handleCableNodeClick(node.id);
                        return;
                    }
                    selectNode(node.id);
                });
                if (canManage) {
                    marker.on('dragend', function (e) {
                        const ll = e.target.getLatLng();
                        updateNodePosition(node.id, ll.lat, ll.lng);
                    });
                }
                markers.set(node.id, marker);
            });

            if (bounds.length) { map.fitBounds(bounds, { padding: [30, 30], maxZoom: 17 }); }

            let selectedNodeId = null;
            let selectedLinkId = null;
            let pickTarget = null;
            let waypointMode = false;
            let waypoints = [];
            let addNodeMode = false;
            let cableMode = false;
            let cableWaypoints = [];
            let cableFromNodeId = null;
            let cableToNodeId = null;

            function showPanel(which) {
                $('topo-empty-panel').hidden = which !== 'empty';
                $('topo-node-panel').hidden = which !== 'node';
                $('topo-link-panel').hidden = which !== 'link';
                $('topo-create-panel').hidden = which !== 'create';
                $('topo-cable-panel').hidden = which !== 'cable';
            }
            function fillParentSelect(select, type, excludeId, selectedId) {
                if (!select) { return; }
                const allowed = graph.allowedParents[type] || [];
                select.innerHTML = '';
                const empty = document.createElement('option');
                empty.value = '';
                empty.textContent = allowed.length ? '— tanpa parent —' : '— (tipe ini adalah akar) —';
                select.append(empty);
                graph.nodes.forEach(function (candidate) {
                    if (candidate.id === excludeId || allowed.indexOf(candidate.type) === -1) { return; }
                    const option = document.createElement('option');
                    option.value = String(candidate.id);
                    option.textContent = (TYPE_LABEL[candidate.type] || candidate.type) + ' · ' + candidate.name;
                    if (candidate.id === selectedId) { option.selected = true; }
                    select.append(option);
                });
                select.disabled = allowed.length === 0;
            }
            function fillPortSelect(select, node, selectedId) {
                if (!select) { return; }
                select.innerHTML = '';
                const none = document.createElement('option');
                none.value = '';
                none.textContent = '— tidak ditentukan —';
                select.append(none);
                (node ? node.ports : []).forEach(function (port) {
                    const option = document.createElement('option');
                    option.value = String(port.id);
                    option.textContent = port.port_type + ' ' + port.port_number;
                    if (port.id === selectedId) { option.selected = true; }
                    select.append(option);
                });
            }
            function fillCableNodeSelect(select, excludeId) {
                if (!select) { return; }
                select.innerHTML = '';
                const none = document.createElement('option');
                none.value = '';
                none.textContent = '— Bebas —';
                select.append(none);
                graph.nodes.forEach(function (node) {
                    if (node.id === excludeId) { return; }
                    const option = document.createElement('option');
                    option.value = String(node.id);
                    option.textContent = (TYPE_LABEL[node.type] || node.type) + ' · ' + node.name;
                    select.append(option);
                });
            }

            function selectNode(id) {
                const node = nodesById.get(id);
                if (!node) { return; }
                selectedNodeId = id;
                selectedLinkId = null;
                stopWaypointMode();
                stopAddNodeMode();
                stopCableMode();
                const panel = $('topo-node-panel');
                showPanel('node');
                $('node-title').textContent = (TYPE_LABEL[node.type] || node.type) + ' · ' + node.name;
                setBadge(panel, 'status', node.is_active ? 'Aktif' : 'Nonaktif', node.is_active ? 'status-active' : 'status-inactive');
                setField(panel, 'type', TYPE_LABEL[node.type] || node.type);
                setField(panel, 'code', node.code || '-');
                setField(panel, 'technology', String(node.technology).toUpperCase());
                setField(panel, 'sort', String(node.sort_order));
                setField(panel, 'lat', node.latitude === null ? '-' : Number(node.latitude).toFixed(7));
                setField(panel, 'lng', node.longitude === null ? '-' : Number(node.longitude).toFixed(7));
                setField(panel, 'coord-source', node.own_coordinates ? 'Diatur manual di peta' : 'Diwarisi dari koordinat pelanggan');
                const parent = node.parent_id ? nodesById.get(node.parent_id) : null;
                setField(panel, 'parent', parent ? (TYPE_LABEL[parent.type] + ' · ' + parent.name) : 'Belum terhubung');
                setField(panel, 'ont-total', String(node.summary.ont_total));
                setField(panel, 'ont-online', String(node.summary.online));
                setField(panel, 'ont-warning', String(node.summary.warning));
                setField(panel, 'ont-offline', String(node.summary.offline));

                $('node-ont-block').hidden = !node.ont;
                if (node.ont) {
                    setField(panel, 'ont-serial', node.ont.serial_number || '-');
                    setField(panel, 'ont-customer', node.ont.customer_name ? node.ont.customer_name + ' (' + node.ont.customer_code + ')' : 'Belum dipetakan');
                    setField(panel, 'ont-rx', fmtRx(node.ont));
                    setField(panel, 'ont-status', STATUS_LABEL[node.ont.status] || node.ont.status);
                    setField(panel, 'ont-inform', node.ont.last_inform_human || '-');
                    $('node-ont-link').href = node.ont.url;
                }

                const portList = $('node-ports');
                portList.innerHTML = '';
                if (!node.ports.length) { portList.append(el('li', 'Belum ada port.', 'ont-muted')); }
                node.ports.forEach(function (port) {
                    portList.append(el('li', port.port_type + ' ' + port.port_number + (port.capacity ? ' · kapasitas ' + port.capacity : '')));
                });

                const incoming = linkByTo.get(id);
                const editLinkButton = $('node-edit-link');
                editLinkButton.hidden = !incoming;
                editLinkButton.onclick = incoming ? function () { selectLink(incoming.id); } : null;

                if (canManage) {
                    $('form-node-edit').action = routes.nodeUpdate.replace('__ID__', String(id));
                    $('edit-name').value = node.name;
                    $('edit-code').value = node.code || '';
                    $('edit-technology').value = node.technology;
                    $('edit-lat').value = node.own_coordinates ? node.latitude : '';
                    $('edit-lng').value = node.own_coordinates ? node.longitude : '';
                    $('edit-sort').value = node.sort_order;
                    $('edit-active').checked = node.is_active;
                    fillParentSelect($('edit-parent'), node.type, node.id, node.parent_id);
                    $('form-port').action = routes.portStore.replace('__ID__', String(id));
                }

                const marker = markers.get(id);
                if (marker) { map.flyTo(marker.getLatLng(), Math.max(map.getZoom(), 16), { duration: 0.6 }); }
            }

            function selectLink(id) {
                const link = linksById.get(id);
                if (!link) { return; }
                selectedLinkId = id;
                selectedNodeId = null;
                stopWaypointMode();
                stopAddNodeMode();
                stopCableMode();
                const from = nodesById.get(link.from_node_id);
                const to = nodesById.get(link.to_node_id);
                const panel = $('topo-link-panel');
                showPanel('link');
                setBadge(
                    panel,
                    'link-status',
                    link.status === 'active' ? 'Aktif' : (link.status === 'inactive' ? 'Nonaktif' : 'Unknown'),
                    link.status === 'active' ? 'status-active' : (link.status === 'inactive' ? 'status-inactive' : 'status-unmapped')
                );
                setField(panel, 'link-type', CABLE_TYPE_LABEL[link.cable_type] || 'Belum ditentukan');
                setField(panel, 'link-from', from ? (TYPE_LABEL[from.type] + ' · ' + from.name) : 'Bebas');
                setField(panel, 'link-to', to ? (TYPE_LABEL[to.type] + ' · ' + to.name) : 'Bebas');
                setField(panel, 'link-from-port', portText(from, link.from_port_id));
                setField(panel, 'link-to-port', portText(to, link.to_port_id));
                const mapMeters = measureMeters(linkPathPoints(link, from, to));
                setField(
                    panel,
                    'link-recorded-length',
                    link.cable_length_m === null ? 'Belum dapat dihitung' : fmtMeters(link.cable_length_m)
                );
                setField(panel, 'link-measured-text', link.measured_attenuation_db === null ? '-' : Number(link.measured_attenuation_db).toFixed(2) + ' dB');
                setField(panel, 'link-calculated-text', link.calculated_attenuation_db === null ? '-' : Number(link.calculated_attenuation_db).toFixed(2) + ' dB');
                setField(panel, 'link-quality', QUALITY_LABEL[link.quality] || link.quality);
                setField(panel, 'link-acs', STATUS_LABEL[link.acs_status] || link.acs_status);
                setField(panel, 'link-summary', summaryText(link.summary));

                if (canManage) {
                    $('form-link').action = routes.linkUpdate.replace('__ID__', String(id));
                    $('link-type-select').value = link.cable_type || '';
                    fillPortSelect($('link-from-port'), from, link.from_port_id);
                    fillPortSelect($('link-to-port'), to, link.to_port_id);
                    $('link-measured').value = link.measured_attenuation_db === null ? '' : link.measured_attenuation_db;
                    $('link-calculated').value = link.calculated_attenuation_db === null ? '' : link.calculated_attenuation_db;
                    $('link-status').value = link.status;
                    waypoints = link.path.slice();
                    syncWaypoints();
                } else {
                    setField(panel, 'link-waypoints', String(link.path.length));
                }
            }

            function syncWaypoints() {
                const area = $('link-path');
                if (area) { area.value = waypoints.length ? JSON.stringify(waypoints) : ''; }
                drawPreview();
            }
            function drawPreview() {
                previewLayer.clearLayers();
                const link = selectedLinkId ? linksById.get(selectedLinkId) : null;
                if (!link) { return; }
                const from = nodesById.get(link.from_node_id);
                const to = nodesById.get(link.to_node_id);
                const a = from ? coords(from) : (link.path && link.path.length ? link.path[0] : null);
                const b = to ? coords(to) : (link.path && link.path.length ? link.path[link.path.length - 1] : null);
                if (!a || !b) { return; }
                L.polyline([a].concat(waypoints, [b]), { color: '#1d4ed8', weight: 2, dashArray: '4 6', interactive: false }).addTo(previewLayer);
                waypoints.forEach(function (point) {
                    L.circleMarker(point, { radius: 4, color: '#1d4ed8', fillColor: '#ffffff', fillOpacity: 1, interactive: false }).addTo(previewLayer);
                });
            }
            function setHint(text) {
                const hint = $('topology-map-hint');
                hint.hidden = !text;
                hint.textContent = text || '';
            }
            function stopWaypointMode() {
                waypointMode = false;
                pickTarget = null;
                const toggle = $('waypoint-toggle');
                if (toggle) { toggle.textContent = 'Tambah waypoint dari peta'; }
                setHint('');
                map.getContainer().classList.remove('is-picking');
            }
            function stopAddNodeMode() {
                addNodeMode = false;
                const btn = $('btn-add-node');
                if (btn) { btn.classList.remove('active'); }
                setHint('');
                map.getContainer().classList.remove('is-picking');
            }
            function stopCableMode() {
                cableMode = false;
                cableWaypoints = [];
                cableFromNodeId = null;
                cableToNodeId = null;
                const btn = $('btn-add-link');
                if (btn) { btn.classList.remove('active'); }
                setHint('');
                map.getContainer().classList.remove('is-picking');
                previewLayer.clearLayers();
            }

            function startAddNodeMode() {
                stopCableMode();
                stopWaypointMode();
                addNodeMode = true;
                const btn = $('btn-add-node');
                if (btn) { btn.classList.add('active'); }
                map.getContainer().classList.add('is-picking');
                setHint('Klik pada peta untuk menempatkan node baru.');
                showPanel('create');
                $('create-lat').value = '';
                $('create-lng').value = '';
                $('create-name').value = '';
                $('create-code').value = '';
                refreshCreateForm();
            }

            function startCableMode() {
                stopAddNodeMode();
                stopWaypointMode();
                cableMode = false;
                cableWaypoints = [];
                cableFromNodeId = null;
                cableToNodeId = null;
                const btn = $('btn-add-link');
                if (btn) { btn.classList.add('active'); }
                showPanel('cable');
                fillCableNodeSelect($('cable-from-node'), null);
                fillCableNodeSelect($('cable-to-node'), null);
                $('cable-from-port').innerHTML = '<option value="">— tidak ditentukan —</option>';
                $('cable-to-port').innerHTML = '<option value="">— tidak ditentukan —</option>';
                $('cable-type').value = '';
                $('cable-status').value = 'unknown';
                $('cable-path').value = '';
                setCableMapLength(null);
                map.getContainer().classList.add('is-picking');
                setHint('Pilih jenis kabel terlebih dahulu pada panel di samping.');
            }

            function activateCableMode() {
                cableMode = true;
                setHint('Klik pada peta untuk menambahkan titik kabel, atau klik node untuk menyambungkan.');
            }

            function setCableMapLength(meters) {
                const field = document.querySelector('#topo-cable-panel [data-field="cable-map-length"]');
                if (field) { field.textContent = fmtMeters(meters); }
            }

            function handleCableNodeClick(nodeId) {
                const node = nodesById.get(nodeId);
                if (!node) { return; }
                if (cableFromNodeId === null) {
                    cableFromNodeId = nodeId;
                    $('cable-from-node').value = String(nodeId);
                    fillPortSelect($('cable-from-port'), node, null);
                    fillCableNodeSelect($('cable-to-node'), nodeId);
                    setHint('Node asal dipilih. Klik node tujuan atau tambah titik kabel di peta.');
                } else if (cableToNodeId === null && nodeId !== cableFromNodeId) {
                    cableToNodeId = nodeId;
                    $('cable-to-node').value = String(nodeId);
                    fillPortSelect($('cable-to-port'), node, null);
                    setHint('Node tujuan dipilih. Tambah titik kabel di peta atau simpan langsung.');
                } else {
                    setHint('Klik node asal atau tujuan untuk mengubah, atau tambah titik kabel di peta.');
                }
                drawCablePreview();
            }

            function drawCablePreview() {
                previewLayer.clearLayers();
                const points = cableWaypoints.slice();
                if (cableFromNodeId !== null) {
                    const a = coords(nodesById.get(cableFromNodeId));
                    if (a) { points.unshift(a); }
                }
                if (cableToNodeId !== null) {
                    const b = coords(nodesById.get(cableToNodeId));
                    if (b) { points.push(b); }
                }
                setCableMapLength(measureMeters(points));
                if (points.length < 2) { return; }
                L.polyline(points, { color: '#1d4ed8', weight: 3, dashArray: '6 4', interactive: false }).addTo(previewLayer);
                cableWaypoints.forEach(function (point) {
                    L.circleMarker(point, { radius: 5, color: '#1d4ed8', fillColor: '#ffffff', fillOpacity: 1, interactive: false }).addTo(previewLayer);
                });
            }

            function updateNodePosition(nodeId, lat, lng) {
                const node = nodesById.get(nodeId);
                if (!node) { return; }
                const formData = new FormData();
                formData.append('_method', 'PATCH');
                formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);
                formData.append('name', node.name);
                formData.append('code', node.code || '');
                formData.append('technology', node.technology);
                formData.append('latitude', lat);
                formData.append('longitude', lng);
                formData.append('sort_order', node.sort_order);
                formData.append('is_active', node.is_active ? '1' : '0');
                if (node.parent_id) { formData.append('parent_id', node.parent_id); }

                fetch(routes.nodeUpdate.replace('__ID__', String(nodeId)), {
                    method: 'POST',
                    body: formData,
                }).then(function (response) {
                    if (response.ok) {
                        node.latitude = lat;
                        node.longitude = lng;
                        node.own_coordinates = true;
                        redrawConnectedCables(nodeId);
                        setHint('Posisi node diperbarui.');
                        setTimeout(function () { setHint(''); }, 2000);
                    } else {
                        setHint('Gagal memperbarui posisi node.');
                        setTimeout(function () { setHint(''); }, 3000);
                        if (markers.has(nodeId)) {
                            const marker = markers.get(nodeId);
                            marker.setLatLng([node.latitude, node.longitude]);
                        }
                    }
                });
            }

            function redrawConnectedCables(nodeId) {
                cableLines.forEach(function (entry, linkId) {
                    const link = entry.link;
                    const from = nodesById.get(link.from_node_id);
                    const to = nodesById.get(link.to_node_id);
                    const a = from ? coords(from) : (link.path && link.path.length ? link.path[0] : null);
                    const b = to ? coords(to) : (link.path && link.path.length ? link.path[link.path.length - 1] : null);
                    if (!a || !b) { return; }
                    const points = [a].concat(link.path, [b]);
                    entry.base.setLatLngs(points);
                });
            }

            map.on('click', function (event) {
                const lat = Number(event.latlng.lat.toFixed(7));
                const lng = Number(event.latlng.lng.toFixed(7));
                if (addNodeMode) {
                    $('create-lat').value = lat;
                    $('create-lng').value = lng;
                    setHint('Koordinat diisi. Lengkapi detail node lalu klik "Tambah Node".');
                    return;
                }
                if (cableMode) {
                    cableWaypoints.push([lat, lng]);
                    $('cable-path').value = JSON.stringify(cableWaypoints);
                    drawCablePreview();
                    return;
                }
                if (pickTarget) {
                    $(pickTarget + '-lat').value = lat;
                    $(pickTarget + '-lng').value = lng;
                    stopWaypointMode();
                    return;
                }
                if (waypointMode) {
                    waypoints.push([lat, lng]);
                    syncWaypoints();
                }
            });

            document.querySelectorAll('[data-pick]').forEach(function (button) {
                button.addEventListener('click', function () {
                    stopWaypointMode();
                    pickTarget = button.getAttribute('data-pick');
                    map.getContainer().classList.add('is-picking');
                    setHint('Klik pada peta untuk mengisi koordinat.');
                });
            });

            if (canManage) {
                $('btn-add-node').addEventListener('click', function () {
                    if (addNodeMode) { stopAddNodeMode(); return; }
                    startAddNodeMode();
                });
                $('btn-add-link').addEventListener('click', function () {
                    if (cableMode) { stopCableMode(); return; }
                    startCableMode();
                });

                $('waypoint-toggle').addEventListener('click', function () {
                    if (waypointMode) { stopWaypointMode(); return; }
                    waypointMode = true;
                    this.textContent = 'Selesai menambah waypoint';
                    map.getContainer().classList.add('is-picking');
                    setHint('Klik pada peta untuk menambah waypoint sesuai jalur kabel.');
                });
                $('waypoint-undo').addEventListener('click', function () { waypoints.pop(); syncWaypoints(); });
                $('waypoint-clear').addEventListener('click', function () { waypoints = []; syncWaypoints(); });
                $('link-path').addEventListener('input', function () {
                    try {
                        const parsed = JSON.parse(this.value || '[]');
                        if (Array.isArray(parsed)) { waypoints = parsed; drawPreview(); }
                    } catch (error) { /* biarkan; validasi dilakukan server */ }
                });

                $('cable-waypoint-undo').addEventListener('click', function () {
                    cableWaypoints.pop();
                    $('cable-path').value = cableWaypoints.length ? JSON.stringify(cableWaypoints) : '';
                    drawCablePreview();
                });
                $('cable-waypoint-clear').addEventListener('click', function () {
                    cableWaypoints = [];
                    $('cable-path').value = '';
                    drawCablePreview();
                });
                $('cable-type').addEventListener('change', function () {
                    if (this.value === '') {
                        cableMode = false;
                        setHint('Pilih jenis kabel terlebih dahulu pada panel di samping.');
                        return;
                    }
                    activateCableMode();
                });
                $('cable-from-node').addEventListener('change', function () {
                    cableFromNodeId = this.value ? Number(this.value) : null;
                    const node = cableFromNodeId ? nodesById.get(cableFromNodeId) : null;
                    fillPortSelect($('cable-from-port'), node, null);
                    fillCableNodeSelect($('cable-to-node'), cableFromNodeId);
                    drawCablePreview();
                });
                $('cable-to-node').addEventListener('change', function () {
                    cableToNodeId = this.value ? Number(this.value) : null;
                    const node = cableToNodeId ? nodesById.get(cableToNodeId) : null;
                    fillPortSelect($('cable-to-port'), node, null);
                    drawCablePreview();
                });

                const createType = $('create-type');
                const refreshCreateForm = function () {
                    fillParentSelect($('create-parent'), createType.value, null, null);
                    $('create-ont-wrap').hidden = createType.value !== 'ont';
                };
                createType.addEventListener('change', refreshCreateForm);
                refreshCreateForm();
            }

            if (focusId && nodesById.has(focusId)) { selectNode(focusId); }
        });
    </script>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $attributes = $__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $component = $__componentOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?>
<?php /**PATH D:\laragon\www\asnet-wifi-management\resources\views/network/topology.blade.php ENDPATH**/ ?>