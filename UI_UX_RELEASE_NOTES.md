# UI/UX refresh

The Blade/Breeze interface has been refreshed with an iOS/macOS-inspired visual system without changing the Laravel stack or business workflows.

## Included

- Sidebar workspace navigation with desktop and mobile drawer behavior.
- Frosted, translucent top bar and compact admin identity card.
- System-font stack inspired by Apple interfaces; no external font dependency is required.
- Quiet neutral canvas, blue accent, rounded cards, subtle borders, and restrained shadows.
- Responsive dashboard metric cards and quick actions.
- Global table, form, button, alert, and guest-login styling for existing Blade pages.
- Existing routes, controllers, data relationships, and business actions remain unchanged by the visual refresh.

## Build on Laragon

From the application directory:

```bash
npm install
npm run build
php artisan optimize:clear
```

If `npm install` is already complete, only run:

```bash
npm run build
php artisan optimize:clear
```

Do not run `migrate:fresh` for this UI refresh.
