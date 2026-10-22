# ASNet final workflow implementation

## Scope implemented

- Admin-only application role and protected business routes.
- Future customer, package, and MikroTik removal actions set records inactive instead of soft-deleting them.
- Existing soft-deleted records can be permanently purged only with an explicit command confirmation.
- Recurring invoice generation is evaluated daily and anchored to the installation date. Dates that do not exist in a month use that month's last day.
- A new invoice is not generated while the customer has an overdue invoice.
- Manual bank/cash/e-wallet payment remains pending until Admin verifies it.
- Payment verification starts restore immediately for an isolated customer. The customer stays isolated until MikroTik confirms the secret is enabled.
- Failed restore remains pending and is retried every minute. The restore WhatsApp link is only produced after live verification succeeds.
- MikroTik PPPoE sync runs every minute as polling fallback and can also be triggered by a signed LAN webhook.
- Webhook security uses `X-ASNet-Mikrotik-Token` and an IP allowlist.
- Automatic isolation uses the configured grace period (default 7 days), processes all eligible records, and is guarded by the real-action kill switch.
- Billing and isolation notes contain `wa.me` links; no WhatsApp gateway is installed.

## Deployment configuration

Copy `.env.example` to `.env`, generate the Laravel application key, and set the database values. Keep these safety defaults until the MikroTik integration has been verified:

```dotenv
MIKROTIK_ALLOW_REAL_ACTIONS=false
MIKROTIK_AUTO_ISOLATION_ENABLED=false
MIKROTIK_GRACE_PERIOD_DAYS=7
MIKROTIK_WEBHOOK_TOKEN=generate-a-long-random-token
MIKROTIK_WEBHOOK_ALLOWED_IPS=192.168.27.1
```

When the live MikroTik connection and RouterOS permissions are confirmed, an Admin may enable real actions deliberately:

```dotenv
MIKROTIK_ALLOW_REAL_ACTIONS=true
MIKROTIK_AUTO_ISOLATION_ENABLED=true
```

## Laragon validation

Run from the application directory:

```bash
composer install
php artisan key:generate
php artisan migrate
php artisan optimize:clear
php artisan test
php artisan route:list -v
php artisan schedule:list
php artisan mikrotik:sync-all-pppoe
```

For the scheduler on Windows/Laragon, run `php artisan schedule:work` as a supervised process, or register the equivalent scheduled task.

## Purge old test soft-deleted records

Back up the database first, inspect the backup, then run this once if the current soft-deleted rows are confirmed to be test data:

```bash
php artisan data:purge-trashed --confirm=PURGE-TRASHED
```

This command is never scheduled and will refuse to run without the exact confirmation string.

## Restore order

```text
Admin verifies payment
  -> enable PPPoE secret
  -> verify live disabled=false
  -> set customer active
  -> provide restore wa.me link
```

If live verification fails, the customer is not activated and no restore-success message/link is produced.
