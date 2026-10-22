@php
    $customerGroupOpen = request()->routeIs('customers.*') || request()->routeIs('map.*');
    $billingGroupOpen = request()->routeIs('invoices.*') || request()->routeIs('payments.*');
    $networkGroupOpen = request()->routeIs('pppoe-accounts.*') || request()->routeIs('ont-devices.*') || request()->routeIs('network.*') || request()->routeIs('mikrotik-devices.*') || request()->routeIs('isolation-logs.*') || request()->routeIs('optical-alerts.*') || request()->routeIs('device-tasks.*');
    $reportGroupOpen = request()->routeIs('reports.*') || request()->routeIs('audit-logs.*') || request()->routeIs('operations.*');
    $settingsGroupOpen = request()->routeIs('internet-packages.*') || request()->routeIs('users.*') || request()->routeIs('genieacs.*');
@endphp

<aside class="app-sidebar" :class="{ 'is-open': sidebarOpen }">
    <div class="sidebar-brand-row">
        <a href="{{ route('dashboard') }}" class="sidebar-brand">
            <img src="{{ asset('images/icons/asnet-sidebar-mark.svg') }}" class="brand-mark-image" alt="ASNet">
            <span>
                <strong>ASNet</strong>
                <small>Billing Management</small>
            </span>
        </a>
        <button type="button" class="sidebar-close" @click="sidebarOpen = false" aria-label="Tutup navigasi">
            <img src="{{ asset('images/icons/close.svg') }}" class="ui-icon ui-icon-close" alt="">
        </button>
    </div>

    @if (Auth::user()->role === 'pelanggan')
        <nav class="sidebar-nav" aria-label="Navigasi pelanggan">
            <a href="{{ route('dashboard') }}" class="sidebar-link {{ request()->routeIs('dashboard') ? 'is-active' : '' }}">
                <span class="sidebar-icon"><img src="{{ asset('images/icons/dashboard.svg') }}" class="sidebar-icon-img" alt=""></span><span>Dashboard Saya</span>
            </a>
            <a href="{{ route('customer.invoices.index') }}" class="sidebar-link {{ request()->routeIs('customer.invoices.*') ? 'is-active' : '' }}">
                <span class="sidebar-icon"><img src="{{ asset('images/icons/invoice.svg') }}" class="sidebar-icon-img" alt=""></span><span>Invoice Saya</span>
            </a>
            <a href="{{ route('customer.payments.index') }}" class="sidebar-link {{ request()->routeIs('customer.payments.*') ? 'is-active' : '' }}">
                <span class="sidebar-icon"><img src="{{ asset('images/icons/payment.svg') }}" class="sidebar-icon-img" alt=""></span><span>Pembayaran Saya</span>
            </a>
        </nav>
    @else
        <nav class="sidebar-nav sidebar-main-nav" aria-label="Navigasi utama">
            <a href="{{ route('dashboard') }}" class="sidebar-link {{ request()->routeIs('dashboard') ? 'is-active' : '' }}">
                <span class="sidebar-icon"><img src="{{ asset('images/icons/dashboard.svg') }}" class="sidebar-icon-img" alt=""></span><span>Dashboard</span>
            </a>

            <div class="sidebar-group" x-data="{ open: @js($customerGroupOpen) }">
                <button type="button" class="sidebar-link sidebar-group-toggle" :class="{ 'is-group-open': open }" @click="open = !open" :aria-expanded="open.toString()">
                    <span class="sidebar-icon"><img src="{{ asset('images/icons/customers.svg') }}" class="sidebar-icon-img" alt=""></span><span>Pelanggan</span><span class="sidebar-chevron" :class="{ 'is-rotated': open }">⌄</span>
                </button>
                <div class="sidebar-subnav" x-cloak x-show="open" x-transition>
                    <a href="{{ route('customers.index') }}" class="sidebar-sublink {{ request()->routeIs('customers.*') ? 'is-active' : '' }}">Daftar Pelanggan</a>
                    <a href="{{ route('map.index') }}" class="sidebar-sublink {{ request()->routeIs('map.*') ? 'is-active' : '' }}">Map Pelanggan</a>
                </div>
            </div>

            @if (in_array(Auth::user()->role, ['owner', 'admin'], true))
                <div class="sidebar-group" x-data="{ open: @js($billingGroupOpen) }">
                    <button type="button" class="sidebar-link sidebar-group-toggle" :class="{ 'is-group-open': open }" @click="open = !open" :aria-expanded="open.toString()">
                        <span class="sidebar-icon"><img src="{{ asset('images/icons/invoice.svg') }}" class="sidebar-icon-img" alt=""></span><span>Billing</span><span class="sidebar-chevron" :class="{ 'is-rotated': open }">⌄</span>
                    </button>
                    <div class="sidebar-subnav" x-cloak x-show="open" x-transition>
                        <a href="{{ route('invoices.index') }}" class="sidebar-sublink {{ request()->routeIs('invoices.*') ? 'is-active' : '' }}">Invoice</a>
                        <a href="{{ route('payments.index') }}" class="sidebar-sublink {{ request()->routeIs('payments.*') ? 'is-active' : '' }}">Pembayaran</a>
                    </div>
                </div>
            @endif

            @if (in_array(Auth::user()->role, ['owner', 'admin', 'teknisi'], true))
                <div class="sidebar-group" x-data="{ open: @js($networkGroupOpen) }">
                    <button type="button" class="sidebar-link sidebar-group-toggle" :class="{ 'is-group-open': open }" @click="open = !open" :aria-expanded="open.toString()">
                        <span class="sidebar-icon"><img src="{{ asset('images/icons/pppoe.svg') }}" class="sidebar-icon-img" alt=""></span><span>Network</span><span class="sidebar-chevron" :class="{ 'is-rotated': open }">⌄</span>
                    </button>
                    <div class="sidebar-subnav" x-cloak x-show="open" x-transition>
                        <a href="{{ route('pppoe-accounts.index') }}" class="sidebar-sublink {{ request()->routeIs('pppoe-accounts.index', 'pppoe-accounts.edit') ? 'is-active' : '' }}">Akun PPPoE</a>
                        <a href="{{ route('pppoe-accounts.sessions') }}" class="sidebar-sublink {{ request()->routeIs('pppoe-accounts.sessions') ? 'is-active' : '' }}">Sesi PPPoE Aktif</a>
                        <a href="{{ route('ont-devices.index') }}" class="sidebar-sublink {{ request()->routeIs('ont-devices.*') ? 'is-active' : '' }}">ONT Devices</a>
                        <a href="{{ route('optical-alerts.index') }}" class="sidebar-sublink {{ request()->routeIs('optical-alerts.*') ? 'is-active' : '' }}">Gangguan</a>
                        <a href="{{ route('device-tasks.index') }}" class="sidebar-sublink {{ request()->routeIs('device-tasks.*') ? 'is-active' : '' }}">Task Perangkat</a>
                        <a href="{{ route('network.topology') }}" class="sidebar-sublink {{ request()->routeIs('network.*') ? 'is-active' : '' }}">Topologi PON</a>
                        @if (in_array(Auth::user()->role, ['owner', 'teknisi'], true))
                            <a href="{{ route('mikrotik-devices.index') }}" class="sidebar-sublink {{ request()->routeIs('mikrotik-devices.*') ? 'is-active' : '' }}">Perangkat MikroTik</a>
                        @endif
                        <a href="{{ route('isolation-logs.index') }}" class="sidebar-sublink {{ request()->routeIs('isolation-logs.*') ? 'is-active' : '' }}">Log Isolasi</a>
                    </div>
                </div>
            @endif

            @if (in_array(Auth::user()->role, ['owner', 'admin'], true))
                <div class="sidebar-group" x-data="{ open: @js($reportGroupOpen) }">
                    <button type="button" class="sidebar-link sidebar-group-toggle" :class="{ 'is-group-open': open }" @click="open = !open" :aria-expanded="open.toString()">
                        <span class="sidebar-icon"><img src="{{ asset('images/icons/report.svg') }}" class="sidebar-icon-img" alt=""></span><span>Laporan</span><span class="sidebar-chevron" :class="{ 'is-rotated': open }">⌄</span>
                    </button>
                    <div class="sidebar-subnav" x-cloak x-show="open" x-transition>
                        <a href="{{ route('reports.index') }}" class="sidebar-sublink {{ request()->routeIs('reports.*') ? 'is-active' : '' }}">Laporan Billing</a>
                        <a href="{{ route('operations.index') }}" class="sidebar-sublink {{ request()->routeIs('operations.*') ? 'is-active' : '' }}">Monitoring Operasional</a>
                        <a href="{{ route('audit-logs.index') }}" class="sidebar-sublink {{ request()->routeIs('audit-logs.*') ? 'is-active' : '' }}">Audit Log</a>
                    </div>
                </div>
            @endif

            @if (Auth::user()->role === 'owner' || Auth::user()->role === 'admin')
                <div class="sidebar-group" x-data="{ open: @js($settingsGroupOpen) }">
                    <button type="button" class="sidebar-link sidebar-group-toggle" :class="{ 'is-group-open': open }" @click="open = !open" :aria-expanded="open.toString()">
                        <span class="sidebar-icon"><img src="{{ asset('images/icons/settings.svg') }}" class="sidebar-icon-img" alt=""></span><span>Pengaturan</span><span class="sidebar-chevron" :class="{ 'is-rotated': open }">⌄</span>
                    </button>
                    <div class="sidebar-subnav" x-cloak x-show="open" x-transition>
                        <a href="{{ route('internet-packages.index') }}" class="sidebar-sublink {{ request()->routeIs('internet-packages.*') ? 'is-active' : '' }}">Paket Internet</a>
                        <a href="{{ route('genieacs.index') }}" class="sidebar-sublink {{ request()->routeIs('genieacs.*') ? 'is-active' : '' }}">GenieACS</a>
                        @if (Auth::user()->role === 'owner')
                            <a href="{{ route('users.index') }}" class="sidebar-sublink {{ request()->routeIs('users.*') ? 'is-active' : '' }}">Manajemen User</a>
                        @endif
                    </div>
                </div>
            @endif
        </nav>
    @endif

    <div class="sidebar-bottom">
        <div class="sidebar-footer-icons">
            <a href="{{ route('profile.edit') }}" class="sidebar-icon-button" title="Pengaturan profil" aria-label="Pengaturan profil">
                <img src="{{ asset('images/icons/settings.svg') }}" alt="">
            </a>
            <a href="#" class="sidebar-icon-button" title="Bantuan" aria-label="Bantuan">
                <img src="{{ asset('images/icons/help.svg') }}" alt="">
            </a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="sidebar-icon-button" title="Keluar" aria-label="Keluar">
                    <img src="{{ asset('images/icons/logout.svg?v=2') }}" alt="">
                </button>
            </form>
        </div>
    </div>
</aside>
