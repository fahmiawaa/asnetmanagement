<?php
    $customerGroupOpen = request()->routeIs('customers.*') || request()->routeIs('map.*');
    $billingGroupOpen = request()->routeIs('invoices.*') || request()->routeIs('payments.*');
    $networkGroupOpen = request()->routeIs('pppoe-accounts.*') || request()->routeIs('ont-devices.*') || request()->routeIs('network.*') || request()->routeIs('mikrotik-devices.*') || request()->routeIs('isolation-logs.*') || request()->routeIs('optical-alerts.*') || request()->routeIs('device-tasks.*');
    $reportGroupOpen = request()->routeIs('reports.*') || request()->routeIs('audit-logs.*') || request()->routeIs('operations.*');
    $settingsGroupOpen = request()->routeIs('internet-packages.*') || request()->routeIs('users.*') || request()->routeIs('genieacs.*');
?>

<aside class="app-sidebar" :class="{ 'is-open': sidebarOpen }">
    <div class="sidebar-brand-row">
        <a href="<?php echo e(route('dashboard')); ?>" class="sidebar-brand">
            <img src="<?php echo e(asset('images/icons/asnet-sidebar-mark.svg')); ?>" class="brand-mark-image" alt="ASNet">
            <span>
                <strong>ASNet</strong>
                <small>Billing Management</small>
            </span>
        </a>
        <button type="button" class="sidebar-close" @click="sidebarOpen = false" aria-label="Tutup navigasi">
            <img src="<?php echo e(asset('images/icons/close.svg')); ?>" class="ui-icon ui-icon-close" alt="">
        </button>
    </div>

    <?php if(Auth::user()->role === 'pelanggan'): ?>
        <nav class="sidebar-nav" aria-label="Navigasi pelanggan">
            <a href="<?php echo e(route('dashboard')); ?>" class="sidebar-link <?php echo e(request()->routeIs('dashboard') ? 'is-active' : ''); ?>">
                <span class="sidebar-icon"><img src="<?php echo e(asset('images/icons/dashboard.svg')); ?>" class="sidebar-icon-img" alt=""></span><span>Dashboard Saya</span>
            </a>
            <a href="<?php echo e(route('customer.invoices.index')); ?>" class="sidebar-link <?php echo e(request()->routeIs('customer.invoices.*') ? 'is-active' : ''); ?>">
                <span class="sidebar-icon"><img src="<?php echo e(asset('images/icons/invoice.svg')); ?>" class="sidebar-icon-img" alt=""></span><span>Invoice Saya</span>
            </a>
            <a href="<?php echo e(route('customer.payments.index')); ?>" class="sidebar-link <?php echo e(request()->routeIs('customer.payments.*') ? 'is-active' : ''); ?>">
                <span class="sidebar-icon"><img src="<?php echo e(asset('images/icons/payment.svg')); ?>" class="sidebar-icon-img" alt=""></span><span>Pembayaran Saya</span>
            </a>
        </nav>
    <?php else: ?>
        <nav class="sidebar-nav sidebar-main-nav" aria-label="Navigasi utama">
            <a href="<?php echo e(route('dashboard')); ?>" class="sidebar-link <?php echo e(request()->routeIs('dashboard') ? 'is-active' : ''); ?>">
                <span class="sidebar-icon"><img src="<?php echo e(asset('images/icons/dashboard.svg')); ?>" class="sidebar-icon-img" alt=""></span><span>Dashboard</span>
            </a>

            <div class="sidebar-group" x-data="{ open: <?php echo \Illuminate\Support\Js::from($customerGroupOpen)->toHtml() ?> }">
                <button type="button" class="sidebar-link sidebar-group-toggle" :class="{ 'is-group-open': open }" @click="open = !open" :aria-expanded="open.toString()">
                    <span class="sidebar-icon"><img src="<?php echo e(asset('images/icons/customers.svg')); ?>" class="sidebar-icon-img" alt=""></span><span>Pelanggan</span><span class="sidebar-chevron" :class="{ 'is-rotated': open }">⌄</span>
                </button>
                <div class="sidebar-subnav" x-cloak x-show="open" x-transition>
                    <a href="<?php echo e(route('customers.index')); ?>" class="sidebar-sublink <?php echo e(request()->routeIs('customers.*') ? 'is-active' : ''); ?>">Daftar Pelanggan</a>
                    <a href="<?php echo e(route('map.index')); ?>" class="sidebar-sublink <?php echo e(request()->routeIs('map.*') ? 'is-active' : ''); ?>">Map Pelanggan</a>
                </div>
            </div>

            <?php if(in_array(Auth::user()->role, ['owner', 'admin'], true)): ?>
                <div class="sidebar-group" x-data="{ open: <?php echo \Illuminate\Support\Js::from($billingGroupOpen)->toHtml() ?> }">
                    <button type="button" class="sidebar-link sidebar-group-toggle" :class="{ 'is-group-open': open }" @click="open = !open" :aria-expanded="open.toString()">
                        <span class="sidebar-icon"><img src="<?php echo e(asset('images/icons/invoice.svg')); ?>" class="sidebar-icon-img" alt=""></span><span>Billing</span><span class="sidebar-chevron" :class="{ 'is-rotated': open }">⌄</span>
                    </button>
                    <div class="sidebar-subnav" x-cloak x-show="open" x-transition>
                        <a href="<?php echo e(route('invoices.index')); ?>" class="sidebar-sublink <?php echo e(request()->routeIs('invoices.*') ? 'is-active' : ''); ?>">Invoice</a>
                        <a href="<?php echo e(route('payments.index')); ?>" class="sidebar-sublink <?php echo e(request()->routeIs('payments.*') ? 'is-active' : ''); ?>">Pembayaran</a>
                    </div>
                </div>
            <?php endif; ?>

            <?php if(in_array(Auth::user()->role, ['owner', 'admin', 'teknisi'], true)): ?>
                <div class="sidebar-group" x-data="{ open: <?php echo \Illuminate\Support\Js::from($networkGroupOpen)->toHtml() ?> }">
                    <button type="button" class="sidebar-link sidebar-group-toggle" :class="{ 'is-group-open': open }" @click="open = !open" :aria-expanded="open.toString()">
                        <span class="sidebar-icon"><img src="<?php echo e(asset('images/icons/pppoe.svg')); ?>" class="sidebar-icon-img" alt=""></span><span>Network</span><span class="sidebar-chevron" :class="{ 'is-rotated': open }">⌄</span>
                    </button>
                    <div class="sidebar-subnav" x-cloak x-show="open" x-transition>
                        <a href="<?php echo e(route('pppoe-accounts.index')); ?>" class="sidebar-sublink <?php echo e(request()->routeIs('pppoe-accounts.index', 'pppoe-accounts.edit') ? 'is-active' : ''); ?>">Akun PPPoE</a>
                        <a href="<?php echo e(route('pppoe-accounts.sessions')); ?>" class="sidebar-sublink <?php echo e(request()->routeIs('pppoe-accounts.sessions') ? 'is-active' : ''); ?>">Sesi PPPoE Aktif</a>
                        <a href="<?php echo e(route('ont-devices.index')); ?>" class="sidebar-sublink <?php echo e(request()->routeIs('ont-devices.*') ? 'is-active' : ''); ?>">ONT Devices</a>
                        <a href="<?php echo e(route('optical-alerts.index')); ?>" class="sidebar-sublink <?php echo e(request()->routeIs('optical-alerts.*') ? 'is-active' : ''); ?>">Gangguan</a>
                        <a href="<?php echo e(route('device-tasks.index')); ?>" class="sidebar-sublink <?php echo e(request()->routeIs('device-tasks.*') ? 'is-active' : ''); ?>">Task Perangkat</a>
                        <a href="<?php echo e(route('network.topology')); ?>" class="sidebar-sublink <?php echo e(request()->routeIs('network.*') ? 'is-active' : ''); ?>">Topologi PON</a>
                        <?php if(in_array(Auth::user()->role, ['owner', 'teknisi'], true)): ?>
                            <a href="<?php echo e(route('mikrotik-devices.index')); ?>" class="sidebar-sublink <?php echo e(request()->routeIs('mikrotik-devices.*') ? 'is-active' : ''); ?>">Perangkat MikroTik</a>
                        <?php endif; ?>
                        <a href="<?php echo e(route('isolation-logs.index')); ?>" class="sidebar-sublink <?php echo e(request()->routeIs('isolation-logs.*') ? 'is-active' : ''); ?>">Log Isolasi</a>
                    </div>
                </div>
            <?php endif; ?>

            <?php if(in_array(Auth::user()->role, ['owner', 'admin'], true)): ?>
                <div class="sidebar-group" x-data="{ open: <?php echo \Illuminate\Support\Js::from($reportGroupOpen)->toHtml() ?> }">
                    <button type="button" class="sidebar-link sidebar-group-toggle" :class="{ 'is-group-open': open }" @click="open = !open" :aria-expanded="open.toString()">
                        <span class="sidebar-icon"><img src="<?php echo e(asset('images/icons/report.svg')); ?>" class="sidebar-icon-img" alt=""></span><span>Laporan</span><span class="sidebar-chevron" :class="{ 'is-rotated': open }">⌄</span>
                    </button>
                    <div class="sidebar-subnav" x-cloak x-show="open" x-transition>
                        <a href="<?php echo e(route('reports.index')); ?>" class="sidebar-sublink <?php echo e(request()->routeIs('reports.*') ? 'is-active' : ''); ?>">Laporan Billing</a>
                        <a href="<?php echo e(route('operations.index')); ?>" class="sidebar-sublink <?php echo e(request()->routeIs('operations.*') ? 'is-active' : ''); ?>">Monitoring Operasional</a>
                        <a href="<?php echo e(route('audit-logs.index')); ?>" class="sidebar-sublink <?php echo e(request()->routeIs('audit-logs.*') ? 'is-active' : ''); ?>">Audit Log</a>
                    </div>
                </div>
            <?php endif; ?>

            <?php if(Auth::user()->role === 'owner' || Auth::user()->role === 'admin'): ?>
                <div class="sidebar-group" x-data="{ open: <?php echo \Illuminate\Support\Js::from($settingsGroupOpen)->toHtml() ?> }">
                    <button type="button" class="sidebar-link sidebar-group-toggle" :class="{ 'is-group-open': open }" @click="open = !open" :aria-expanded="open.toString()">
                        <span class="sidebar-icon"><img src="<?php echo e(asset('images/icons/settings.svg')); ?>" class="sidebar-icon-img" alt=""></span><span>Pengaturan</span><span class="sidebar-chevron" :class="{ 'is-rotated': open }">⌄</span>
                    </button>
                    <div class="sidebar-subnav" x-cloak x-show="open" x-transition>
                        <a href="<?php echo e(route('internet-packages.index')); ?>" class="sidebar-sublink <?php echo e(request()->routeIs('internet-packages.*') ? 'is-active' : ''); ?>">Paket Internet</a>
                        <a href="<?php echo e(route('genieacs.index')); ?>" class="sidebar-sublink <?php echo e(request()->routeIs('genieacs.*') ? 'is-active' : ''); ?>">GenieACS</a>
                        <?php if(Auth::user()->role === 'owner'): ?>
                            <a href="<?php echo e(route('users.index')); ?>" class="sidebar-sublink <?php echo e(request()->routeIs('users.*') ? 'is-active' : ''); ?>">Manajemen User</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </nav>
    <?php endif; ?>

    <div class="sidebar-bottom">
        <div class="sidebar-footer-icons">
            <a href="<?php echo e(route('profile.edit')); ?>" class="sidebar-icon-button" title="Pengaturan profil" aria-label="Pengaturan profil">
                <img src="<?php echo e(asset('images/icons/settings.svg')); ?>" alt="">
            </a>
            <a href="#" class="sidebar-icon-button" title="Bantuan" aria-label="Bantuan">
                <img src="<?php echo e(asset('images/icons/help.svg')); ?>" alt="">
            </a>
            <form method="POST" action="<?php echo e(route('logout')); ?>">
                <?php echo csrf_field(); ?>
                <button type="submit" class="sidebar-icon-button" title="Keluar" aria-label="Keluar">
                    <img src="<?php echo e(asset('images/icons/logout.svg?v=2')); ?>" alt="">
                </button>
            </form>
        </div>
    </div>
</aside>
<?php /**PATH D:\laragon\www\asnet-wifi-management\resources\views/layouts/navigation.blade.php ENDPATH**/ ?>