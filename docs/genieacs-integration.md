# Integrasi GenieACS read-only

ASNet membaca snapshot ONT melalui NBI GenieACS. Aplikasi tidak membaca MongoDB
GenieACS secara langsung dan tidak mengambil parameter password.

## Prasyarat jaringan

- Laravel dapat mengakses NBI GenieACS pada jaringan internal.
- Port NBI `7557` tidak dibuka ke internet.
- Gunakan nama service Docker, alamat LAN, atau reverse proxy internal.
- Jika Basic Auth dipakai, siapkan akun read-only khusus aplikasi.

## Konfigurasi

Jalankan migration, lalu simpan dan uji koneksi:

```bash
php artisan migrate --force
php artisan genieacs:configure "GenieACS Utama" "http://genieacs:7557"
```

Jika NBI memakai Basic Auth:

```bash
php artisan genieacs:configure "GenieACS Utama" "http://genieacs:7557" \
  --username=nbi-reader
```

Password diminta secara tersembunyi dan disimpan dengan encrypted cast Laravel.
Jangan memasukkan password ke argumen command, `.env`, log, atau dokumentasi.

## Sinkronisasi

Uji sinkronisasi manual:

```bash
php artisan genieacs:sync-onts
```

Scheduler menjalankannya setiap dua menit. Production harus menjalankan scheduler
dan queue worker sesuai dokumentasi deployment aplikasi.

Daftar ONT membaca tabel `ont_devices`, bukan meminta payload GenieACS langsung
dari browser. RX power masih disimpan sebagai nilai mentah pada Sprint 1 dan baru
dinormalisasi pada Sprint 2.