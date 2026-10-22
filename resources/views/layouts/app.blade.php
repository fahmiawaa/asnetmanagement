<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <link rel="icon" type="image/svg+xml" href="{{ asset('images/icons/asnet-mark.svg') }}">
        <title>{{ config('app.name', 'ASNet Billing') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <link rel="stylesheet" href="{{ asset('css/asnet-ui-reference.css?v=22') }}">
    </head>
    <body>
        @php
            $unreadNotificationCount = Auth::user()->unreadNotifications()->count();
        @endphp
        <div class="app-shell {{ Auth::user()->role === 'pelanggan' ? 'customer-shell' : '' }}" x-data="{ sidebarOpen: false }">
            @include('layouts.navigation')

            <div class="app-main">
                <header class="app-topbar">
                    <div class="topbar-left">
                        <button type="button" class="mobile-menu-button" @click="sidebarOpen = true" aria-label="Buka navigasi">
                            <img src="{{ asset('images/icons/menu.svg') }}" alt="">
                        </button>
                        <div class="topbar-heading">
                            @isset($header)
                                {{ $header }}
                            @else
                                <span class="topbar-spacer" aria-hidden="true"></span>
                            @endisset
                        </div>
                    </div>

                    @if (Auth::user()->role !== 'pelanggan')
                        <form class="topbar-search" method="GET" action="{{ route('customers.index') }}">
                            <img src="{{ asset('images/icons/search.svg') }}" width="16" height="16" alt="">
                            <input type="search" name="q" placeholder="Cari pelanggan..." aria-label="Cari pelanggan">
                        </form>
                    @endif

                    <div class="topbar-right">
                        <div class="topbar-notification-wrap" data-notification-center>
                            <button type="button" class="topbar-notification" data-notification-toggle aria-label="Buka notifikasi" aria-expanded="false">
                                <img src="{{ asset('images/icons/notification.svg') }}" alt="">
                                <span data-notification-badge @if ($unreadNotificationCount === 0) hidden @endif>{{ $unreadNotificationCount > 99 ? '99+' : $unreadNotificationCount }}</span>
                            </button>
                            <section class="notification-popover" data-notification-popover hidden aria-label="Notifikasi">
                                <div class="notification-popover-heading">
                                    <div>
                                        <strong>Notifikasi</strong>
                                        <small data-notification-summary>Memuat notifikasi...</small>
                                    </div>
                                    <button type="button" class="notification-mark-all" data-notification-mark-all> Tandai semua dibaca </button>
                                </div>
                                <div class="notification-list" data-notification-list>
                                    <p class="notification-empty">Memuat notifikasi...</p>
                                </div>
                                <button type="button" class="notification-browser-button" data-notification-browser>
                                    <img src="{{ asset('images/icons/notification.svg') }}" alt="">
                                    <span>Aktifkan notifikasi browser</span>
                                </button>
                            </section>
                        </div>
                        <a href="{{ route('profile.edit') }}" class="topbar-user" aria-label="Buka profil dan pengaturan akun">
                            <span class="avatar">{{ strtoupper(substr(Auth::user()->name ?? 'A', 0, 1)) }}</span>
                            <span class="topbar-user-copy">
                                <strong>{{ Auth::user()->name ?? 'Admin' }}</strong>
                                <small>{{ Auth::user()->role === 'pelanggan' ? 'Pelanggan' : ucfirst(Auth::user()->role ?? 'Administrator') }}</small>
                            </span>
                            <img class="topbar-chevron" src="{{ asset('images/icons/chevron-down.svg') }}" alt="">
                        </a>
                    </div>
                </header>

                <main class="app-content">
                    @if (session('success'))
                        <div class="flash flash-success" role="status">
                            <span class="flash-icon"><img src="{{ asset('images/icons/check.svg') }}" alt=""></span>
                            <span>{{ session('success') }}</span>
                        </div>
                    @endif

                    @if (session('error'))
                        <div class="flash flash-error" role="alert">
                            <span class="flash-icon"><img src="{{ asset('images/icons/alert.svg') }}" alt=""></span>
                            <span>{{ session('error') }}</span>
                        </div>
                    @endif

                    {{ $slot }}
                </main>
            </div>

            @if (Auth::user()->role === 'pelanggan')
                <nav class="customer-portal-bottom-nav customer-global-bottom-nav" aria-label="Navigasi portal pelanggan">
                    <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'is-active' : '' }}">
                        <img src="{{ asset('images/icons/dashboard.svg') }}" alt=""><span>Dashboard</span>
                    </a>
                    <a href="{{ route('customer.invoices.index') }}" class="{{ request()->routeIs('customer.invoices.*') ? 'is-active' : '' }}">
                        <img src="{{ asset('images/icons/invoice.svg') }}" alt=""><span>Tagihan</span>
                    </a>
                    <a href="{{ route('customer.payments.index') }}" class="{{ request()->routeIs('customer.payments.*') ? 'is-active' : '' }}">
                        <img src="{{ asset('images/icons/payment.svg') }}" alt=""><span>Pembayaran</span>
                    </a>
                    <a href="{{ route('profile.edit') }}" class="{{ request()->routeIs('profile.*') ? 'is-active' : '' }}">
                        <img src="{{ asset('images/icons/customers.svg') }}" alt=""><span>Profil</span>
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" aria-label="Keluar">
                            <img src="{{ asset('images/icons/logout.svg?v=2') }}" alt=""><span>Keluar</span>
                        </button>
                    </form>
                </nav>
            @endif

            <div class="sidebar-backdrop" x-show="sidebarOpen" x-transition.opacity @click="sidebarOpen = false" style="display: none;"></div>
        </div>
        <script>
            (() => {
                const center = document.querySelector('[data-notification-center]');
                if (!center) return;

                const toggle = center.querySelector('[data-notification-toggle]');
                const popover = center.querySelector('[data-notification-popover]');
                const list = center.querySelector('[data-notification-list]');
                const badge = center.querySelector('[data-notification-badge]');
                const summary = center.querySelector('[data-notification-summary]');
                const markAll = center.querySelector('[data-notification-mark-all]');
                const browserButton = center.querySelector('[data-notification-browser]');
                const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
                const feedUrl = @json(route('notifications.feed'));
                const readAllUrl = @json(route('notifications.read-all'));
                const serviceWorkerUrl = @json(asset('sw.js'));
                const notificationIcon = @json(asset('images/icons/asnet-mark.svg'));
                let knownIds = new Set();
                let firstLoad = true;
                let serviceWorkerRegistration = null;

                const escapeHtml = (value) => String(value ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');

                const formatNotification = (item) => `
                    <a class="notification-item ${item.read ? '' : 'is-unread'}" href="${escapeHtml(item.url || '#')}" data-notification-id="${escapeHtml(item.id)}">
                        <span class="notification-item-icon notification-level-${escapeHtml(item.level || 'info')}">
                            <img src="${notificationIcon}" alt="">
                        </span>
                        <span class="notification-item-copy">
                            <strong>${escapeHtml(item.title)}</strong>
                            <span>${escapeHtml(item.message)}</span>
                            <small>${escapeHtml(item.created_label || '')}</small>
                        </span>
                    </a>`;

                const render = (items, unreadCount) => {
                    if (!items.length) {
                        list.innerHTML = '<p class="notification-empty">Belum ada notifikasi.</p>';
                    } else {
                        list.innerHTML = items.map(formatNotification).join('');
                    }

                    summary.textContent = unreadCount > 0
                        ? `${unreadCount} notifikasi belum dibaca`
                        : 'Semua notifikasi sudah dibaca';

                    if (unreadCount > 0) {
                        badge.hidden = false;
                        badge.textContent = unreadCount > 99 ? '99+' : unreadCount;
                    } else {
                        badge.hidden = true;
                        badge.textContent = '';
                    }
                };

                const showBrowserNotification = async (item) => {
                    if (!('Notification' in window) || Notification.permission !== 'granted') return;

                    try {
                        serviceWorkerRegistration ??= await navigator.serviceWorker.getRegistration();
                        if (serviceWorkerRegistration) {
                            await serviceWorkerRegistration.showNotification(item.title, {
                                body: item.message,
                                icon: notificationIcon,
                                tag: item.id,
                                data: { url: item.url || window.location.href },
                            });
                        } else {
                            new Notification(item.title, { body: item.message, icon: notificationIcon });
                        }
                    } catch (error) {
                        console.warn('Notifikasi browser tidak dapat ditampilkan.', error);
                    }
                };

                const loadNotifications = async () => {
                    try {
                        const response = await fetch(feedUrl, {
                            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                            credentials: 'same-origin',
                        });
                        if (!response.ok) return;

                        const data = await response.json();
                        const items = Array.isArray(data.notifications) ? data.notifications : [];
                        if (!firstLoad) {
                            items.filter((item) => !knownIds.has(item.id) && !item.read)
                                .slice(0, 3)
                                .forEach(showBrowserNotification);
                        }
                        knownIds = new Set(items.map((item) => item.id));
                        firstLoad = false;
                        render(items, Number(data.unread_count || 0));
                    } catch (error) {
                        if (firstLoad) list.innerHTML = '<p class="notification-empty">Notifikasi belum dapat dimuat.</p>';
                    }
                };

                toggle.addEventListener('click', async () => {
                    const isHidden = popover.hidden;
                    popover.hidden = !isHidden;
                    toggle.setAttribute('aria-expanded', String(isHidden));
                    if (isHidden) await loadNotifications();
                });

                markAll.addEventListener('click', async () => {
                    await fetch(readAllUrl, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
                        credentials: 'same-origin',
                    });
                    await loadNotifications();
                });

                list.addEventListener('click', async (event) => {
                    const item = event.target.closest('[data-notification-id]');
                    if (!item) return;
                    const id = item.dataset.notificationId;
                    await fetch(`{{ url('/notifications') }}/${encodeURIComponent(id)}/read`, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
                        credentials: 'same-origin',
                    });
                });

                browserButton.addEventListener('click', async () => {
                    if (!('Notification' in window)) {
                        browserButton.querySelector('span').textContent = 'Browser tidak mendukung notifikasi';
                        return;
                    }

                    const permission = await Notification.requestPermission();
                    if (permission !== 'granted') {
                        browserButton.querySelector('span').textContent = 'Izin notifikasi belum diberikan';
                        return;
                    }

                    if ('serviceWorker' in navigator) {
                        serviceWorkerRegistration = await navigator.serviceWorker.register(serviceWorkerUrl);
                    }
                    browserButton.querySelector('span').textContent = 'Notifikasi browser aktif';
                    await showBrowserNotification({
                        title: 'Notifikasi ASNet aktif',
                        message: 'Anda akan menerima pemberitahuan aktivitas terbaru.',
                        id: 'asnet-notification-enabled',
                    });
                });

                document.addEventListener('click', (event) => {
                    if (!center.contains(event.target)) {
                        popover.hidden = true;
                        toggle.setAttribute('aria-expanded', 'false');
                    }
                });

                loadNotifications();
                window.setInterval(loadNotifications, 60000);
            })();
        </script>
    </body>
</html>
