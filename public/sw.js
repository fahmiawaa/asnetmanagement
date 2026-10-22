const ASNET_NOTIFICATION_CACHE = 'asnet-notification-v1';

self.addEventListener('install', () => self.skipWaiting());

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('push', (event) => {
    let payload = {
        title: 'ASNet Billing',
        body: 'Ada aktivitas baru di workspace ASNet.',
        url: '/dashboard',
    };

    try {
        if (event.data) payload = { ...payload, ...event.data.json() };
    } catch (error) {
        // Payload non-JSON tetap menggunakan pesan default.
    }

    event.waitUntil(
        self.registration.showNotification(payload.title, {
            body: payload.body || payload.message,
            icon: '/images/icons/asnet-mark.svg',
            badge: '/favicon.ico',
            tag: payload.tag || 'asnet-notification',
            data: { url: payload.url || '/dashboard' },
        })
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const targetUrl = new URL(
        event.notification.data?.url || '/dashboard',
        self.location.origin
    ).href;

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
            const existing = clients.find((client) => 'focus' in client);
            if (existing) {
                existing.navigate(targetUrl);
                return existing.focus();
            }
            return self.clients.openWindow(targetUrl);
        })
    );
});