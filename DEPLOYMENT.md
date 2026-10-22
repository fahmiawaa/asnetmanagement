# ASNet WiFi Management — Deployment Guide

## 1. Install dependencies

Run from the project directory:

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
```

Do not copy an existing `vendor` or `node_modules` directory between
different operating systems.

## 2. Configure the environment

```bash
cp .env.example .env
php artisan key:generate
```

Production must use at least:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_TIMEZONE=Asia/Jakarta
ASNET_PUBLIC_REGISTRATION=false
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
MIKROTIK_ALLOW_REAL_ACTIONS=false
MIKROTIK_AUTO_ISOLATION_ENABLED=false
```

Set a long random `MIKROTIK_WEBHOOK_TOKEN` and restrict
`MIKROTIK_WEBHOOK_ALLOWED_IPS` to the trusted network.

Do not set `SESSION_SECURE_COOKIE=true` on a plain HTTP development URL.
Production must use HTTPS before this flag is enabled.

## 3. Prepare the database

The default production database name is:

```text
asnet_billing_pro
```

Create the database and a dedicated application user before running migrations:

```sql
CREATE DATABASE asnet_billing_pro
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

CREATE USER 'asnet_app'@'localhost'
IDENTIFIED BY 'replace-with-a-long-password';

GRANT ALL PRIVILEGES
ON asnet_billing_pro.*
TO 'asnet_app'@'localhost';

FLUSH PRIVILEGES;
```

```bash
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Create the first administrator through the CLI:

```bash
php artisan admin:create admin@example.com --name="ASNet Admin"
```

Do not enable public registration in production.

## 4. Existing payment proofs

If the previous version stored proofs on the public disk, migrate them once:

```bash
php artisan files:migrate-payment-proofs --confirm=MIGRATE-PAYMENT-PROOFS
```

Back up the database and storage before running the migration command.

## 5. Scheduler and queue

Run the Laravel scheduler continuously:

```bash
php artisan schedule:work
```

If queue-backed jobs are enabled:

```bash
php artisan queue:work --tries=3 --timeout=120
```

On Windows/Laragon, configure both processes under a supervisor or Windows
Task Scheduler.

## 6. Offsite backup

ASNet membuat dump MySQL terkompresi (`.sql.gz`), checksum SHA-256, manifest,
salinan lokal, dan salinan offsite. Pilih salah satu tujuan berikut.

### S3 / MinIO / Cloudflare R2

```dotenv
BACKUP_OFFSITE_ENABLED=true
BACKUP_OFFSITE_DISK=offsite
BACKUP_OFFSITE_PATH=asnet-production
BACKUP_OFFSITE_RETENTION_DAYS=30
OFFSITE_DRIVER=s3
OFFSITE_ACCESS_KEY_ID=...
OFFSITE_SECRET_ACCESS_KEY=...
OFFSITE_DEFAULT_REGION=auto
OFFSITE_BUCKET=...
OFFSITE_ENDPOINT=https://...
OFFSITE_USE_PATH_STYLE_ENDPOINT=false
```

### NAS atau server backup yang ter-mount

Folder harus berada di perangkat atau server lain, bukan disk server aplikasi.

```dotenv
BACKUP_OFFSITE_ENABLED=true
BACKUP_OFFSITE_DISK=offsite
OFFSITE_DRIVER=local
OFFSITE_LOCAL_ROOT=/mnt/asnet-backup
```

### Enkripsi arsip backup

Arsip dapat dienkripsi (sodium secretstream) sebelum disalin offsite. Simpan
kunci di secret manager, bukan di repository. Tanpa kunci ini arsip tidak dapat
dipulihkan.

```dotenv
BACKUP_ENCRYPTION_ENABLED=true
BACKUP_ENCRYPTION_KEY=base64:...
```

Buat kunci sekali:

```bash
php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"
```

Uji backup dan periksa hasil verifikasinya:

```bash
php artisan asnet:backup
```

Uji restore berkala pada database terpisah. Drill menjalankan verifikasi
ukuran/checksum, dekripsi bila arsip terenkripsi, inventaris tabel, restore ke
database sementara `asnet_drill_*`, dan perbandingan jumlah baris:

```bash
php artisan asnet:restore-drill --strict
```

Tanpa `--strict`, fase restore yang dilewati (mis. tanpa MySQL client) hanya
menghasilkan peringatan. Jangan hanya mengandalkan keberadaan file. Minimal
sebulan sekali jalankan drill dan simpan keluarannya sebagai bukti.

## 7. Monitoring dan alerting

Konfigurasikan minimal satu kanal alert (email atau webhook):

```dotenv
OPERATIONS_HEALTH_TOKEN=ganti-dengan-token-acak-panjang
OPERATIONS_ALERT_EMAILS=noc@example.com,owner@example.com
OPERATIONS_ALERT_WEBHOOK_URL=https://alert-gateway.example/asnet
OPERATIONS_ALERT_WEBHOOK_TOKEN=...
OPERATIONS_ALERT_COOLDOWN_MINUTES=30
```

Jika menggunakan email, ubah `MAIL_MAILER=log` ke SMTP produksi. Dashboard
internal tersedia di menu **Laporan → Monitoring Operasional**.

Pantau endpoint berikut dari Uptime Kuma, Better Stack, atau monitor eksternal
lain setiap 5 menit:

```text
GET https://billing.example.com/health
X-ASNet-Health-Token: token-yang-sama
```

Endpoint mengembalikan HTTP `503` bila database, backup, scheduler, MikroTik,
queue, atau auto-isolir berada dalam kondisi kritis. Monitor eksternal wajib
digunakan karena scheduler yang mati tidak dapat mengirim alert dari dirinya
sendiri.

Jalankan pemeriksaan kesiapan akhir:

```bash
php artisan asnet:preflight --production
php artisan asnet:health-check
```

## 8. Safe rollout

### Staging dengan data aman

Staging memakai data contoh atau salinan database produksi yang disamarkan.
Jangan pernah menyalin PII asli ke staging.

```bash
# Opsi A: data contoh. Naikkan jumlah pelanggan untuk uji beban 300 ONT.
STAGING_CUSTOMER_COUNT=300 php artisan db:seed --class=StagingSeeder

# Opsi B: salinan database produksi, lalu samarkan PII/kredensial.
php artisan asnet:mask-staging --dry-run   # tinjau rencana tanpa mengubah data
php artisan asnet:mask-staging --force     # jalankan di environment non-production
```

`asnet:mask-staging` menolak environment `production`, menghapus seluruh baris
`sessions`, dan mengganti PII, kredensial terenkripsi, serta identitas perangkat
dengan nilai deterministik agar batas unik tetap aman.

### Pilot

1. Keep real MikroTik actions disabled.
2. Run read-only PPPoE synchronization.
3. Test one lab account.
4. Run a 5–10 customer pilot.
5. Review all isolation and restore logs.
6. Enable real actions only after the pilot is approved.

### Uji backup dan restore sebelum pilot

```bash
php artisan asnet:backup
php artisan asnet:restore-drill --strict
```

Simpan keluaran drill sebagai bukti sebelum pilot dan sebelum rilis.

### Rollback kode

```bash
git checkout <tag-rilis-sebelumnya>
composer install --no-dev --optimize-autoloader
php artisan optimize:clear
php artisan asnet:health-check
```

Rollback aplikasi tanpa menyentuh data aman dilakukan selama tidak ada migrasi
breaking. Untuk migrasi breaking, siapkan migrasi balik dan uji di staging
lebih dulu.

### Emergency restore

1. `php artisan down`
2. Verifikasi arsip sebelum menyentuh database utama:
   `php artisan asnet:restore-drill --backup=<arsip> --keep-database`
3. Pulihkan ke database utama hanya setelah arsip lolos verifikasi:
   `gzip -dc <arsip>.sql.gz | mysql -u <user> -p <database>`. Untuk arsip
   terenkripsi, jalankan drill dengan `--keep-database` lalu salin tabel dari
   database `asnet_drill_*`.
4. `php artisan asnet:health-check`, lalu `php artisan up`.
5. Catat insiden dan alasan restore pada audit log operasional.

Never run `migrate:fresh` against a production database.