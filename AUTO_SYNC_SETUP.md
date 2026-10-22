# Automatic MikroTik PPPoE Synchronization

## What was added

- `mikrotik:sync-pppoe {deviceId}` now records whether each local account is still present on MikroTik.
- `mikrotik:sync-all-pppoe` synchronizes every registered MikroTik device.
- Laravel Scheduler runs `mikrotik:sync-all-pppoe` every minute.
- The PPPoE page has a manual `Sinkronkan Sekarang` action and refreshes the page every 60 seconds.
- Successful real isolation and restore update the local PPPoE snapshot immediately after live verification.
- Accounts missing from a successful MikroTik snapshot are retained for audit but shown as `Tidak ditemukan`.

## Windows Task Scheduler

Laravel Scheduler needs a Windows task that runs once per minute.

Program:

```text
D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe
```

Arguments:

```text
artisan schedule:run
```

Start in:

```text
D:\laragon\www\asnet-billing-management
```

Set the task to run every minute, whether or not the user is logged in, using the same Windows account that can access the Laravel project and MikroTik network.

## Manual verification

```bash
php artisan migrate
php artisan mikrotik:sync-all-pppoe
php artisan schedule:list
```

The sync uses the existing encrypted MikroTik credentials and the read-only PPPoE print API. It does not disable, enable, or disconnect accounts.

Keep the real-action safety switch disabled unless an approved pilot action is being executed:

```env
MIKROTIK_ALLOW_REAL_ACTIONS=false
```
