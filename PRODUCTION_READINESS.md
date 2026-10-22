# ASNet Billing Management — Production Readiness

## Current decision

**NO-GO until the release gates below are completed.**

The application now applies the first production-hardening changes:

- Public registration is disabled by default.
- An administrator can be bootstrapped with `php artisan admin:create`.
- Existing public payment proofs can be moved with
  `php artisan files:migrate-payment-proofs --confirm=MIGRATE-PAYMENT-PROOFS`.
- Business routes require authentication, verified email, and admin role.
- Payment verification is transaction-safe and uses row locking.
- Payment proof uploads use private storage.
- Customer status can no longer be edited through the general customer form.
- Invoice updates validate that the due date is not before the billing period.
- Stale approved isolation candidates are cancelled before a live disable action.
- Router action commands report actual execution failures.
- Application timezone defaults to `Asia/Jakarta`.
- Main operational lists support server-side search, filters, and pagination.
- Backup harian menghasilkan arsip gzip, checksum SHA-256, manifest, retensi,
  dan salinan terverifikasi ke S3/MinIO/R2 atau NAS di luar server.
- Health check memantau database, cache, disk, queue, MikroTik, auto-isolir,
  backup, dan heartbeat scheduler.
- Alert operasional dikirim ke notifikasi aplikasi, email, dan/atau webhook
  dengan cooldown agar tidak membanjiri operator.
- Dashboard Monitoring Operasional dan endpoint `/health` tersedia.
- Akun nonaktif ditolak oleh middleware global, termasuk pada dashboard,
  profil, dan notifikasi.
- Respons web menyertakan security headers dasar dan HSTS pada HTTPS produksi.
- Command preflight menolak registrasi publik serta session cookie produksi
  yang tidak aman.

## Mandatory release gates

### Security

- [ ] Rotate every secret that has ever been included in an archive or log.
- [ ] Deploy with `APP_ENV=production` and `APP_DEBUG=false`.
- [ ] Keep `.env`, logs, `vendor`, and `node_modules` out of release archives.
- [ ] Confirm public registration remains disabled.
- [ ] Configure HTTPS, `SESSION_SECURE_COOKIE=true`, HTTP-only/SameSite cookie,
  server firewall rules, dan token `/health`.
- [ ] Aktifkan backup offsite dan verifikasi file, ukuran, serta checksum.
- [ ] Aktifkan enkripsi arsip (`BACKUP_ENCRYPTION_ENABLED=true`) dan simpan
  `BACKUP_ENCRYPTION_KEY` di secret manager, bukan di repository.
- [ ] Konfigurasikan minimal satu kanal alert nyata (SMTP atau webhook).

### Billing and data integrity

- [ ] Run payment concurrency tests.
- [ ] Confirm duplicate pending payments are rejected.
- [ ] Confirm paid and cancelled invoices cannot be verified again.
- [ ] Add and verify invoice-period uniqueness for the production database.
- [ ] Verify database backup and restore before enabling real actions.

### MikroTik

- [ ] Test read-only sync with the real RouterOS version.
- [ ] Test disable, disconnect, verification, and retry with a lab account.
- [ ] Test enable, verification, and retry with a lab account.
- [ ] Confirm the RouterOS API user has only the required permissions.
- [ ] Keep `MIKROTIK_ALLOW_REAL_ACTIONS=false` until pilot approval.

### Operational pilot

- [ ] Siapkan staging dengan data tersamarkan: `asnet:mask-staging --force`
  pada salinan database, atau `StagingSeeder` untuk data contoh.
- [ ] Uji beban 300 ONT pada staging dan catat p95 daftar ONT (target ≤ 2 detik;
  penjaga regresi otomatis ada di `OntDeviceControllerTest`).
- [ ] Run a limited pilot with no more than 5–10 customers.
- [ ] Review every isolation and restore log manually.
- [ ] Monitor scheduler, queue, logs, and database locks.
- [ ] Hubungkan `/health` ke monitor eksternal dari jaringan berbeda.
- [ ] Jalankan `asnet:restore-drill --strict` pada arsip produksi terbaru dan
  simpan keluarannya sebagai bukti.
- [ ] Document rollback and emergency re-enable procedures (lihat
  `DEPLOYMENT.md` bagian 8).
- [ ] Obtain approval from the product owner, technical lead, network administrator, and QA.

## Recommended production configuration

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

Enable real actions only after the staging and pilot gates have passed.