# ASNet 700 — Offsite Backup, Monitoring, dan Alerting

## Perubahan utama

- Backup MySQL harian sekarang dikompres sebagai `.sql.gz`.
- Setiap backup memiliki checksum SHA-256 dan `latest.json`.
- Backup diverifikasi setelah ditulis ke disk lokal dan tujuan offsite.
- Tujuan offsite mendukung S3, MinIO, Cloudflare R2, atau NAS yang ter-mount.
- Retensi lokal dan offsite dapat diatur terpisah.
- Kegagalan backup memicu alert operasional.
- Health check memantau database, cache, disk, queue, MikroTik, auto-isolir,
  backup, serta heartbeat scheduler.
- Alert mendukung notifikasi aplikasi, email, dan webhook dengan cooldown.
- Dashboard baru: **Laporan → Monitoring Operasional**.
- Endpoint monitor eksternal: `GET /health`.
- Preflight produksi mewajibkan backup offsite, token health, dan kanal alert.

## Setelah menyalin rilis

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Salin konfigurasi backup dan monitoring dari `.env.example` ke `.env`, lalu:

```bash
php artisan asnet:backup
php artisan asnet:health-check
php artisan asnet:preflight --production
```

Pastikan `schedule:work` atau cron `schedule:run` aktif. Hubungkan `/health`
ke monitor eksternal agar scheduler yang mati tetap terdeteksi.

Panduan lengkap tersedia di `DEPLOYMENT.md`.