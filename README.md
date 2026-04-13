<div align="center">

# YAP - Yet Another Panel

**A modern subscription panel for VPN / proxy services**

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-blue.svg)](https://php.net)
[![Laravel](https://img.shields.io/badge/Laravel-12-red.svg)](https://laravel.com)
[![React](https://img.shields.io/badge/React-18-blue.svg)](https://reactjs.org)
[![Filament](https://img.shields.io/badge/Filament-5-orange.svg)](https://filamentphp.com)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind-4-38B2AC.svg)](https://tailwindcss.com)

[Highlights](#-highlights) • [Stack](#-stack) • [Quick-Start](#-quick-start) • [Routes](#-main-routes) • [Upgrade-Status](#-upgrade-status)

</div>

---

## About

YAP (Yet Another Panel) is a Laravel-based panel for selling and managing VPN / proxy subscriptions.

It combines a React + Inertia frontend for customers with a Filament admin panel for daily operations.

## Highlights

- customer-facing dashboard, recharge, package purchase, payment history, and profile flows
- Clash subscription delivery via `/clash/{uuid}/yap.yaml`
- admin management for users, Vmess servers, and relay servers
- payment integrations for Alipay, BEPUSDT, and Stripe
- GitHub OAuth login and GitHub Sponsors webhook support
- Laravel 12, React 18, Filament 5, Livewire 4, Tailwind CSS 4

## Stack

### Backend

- PHP 8.2+
- Laravel 12
- Filament 5
- Livewire 4
- Laravel Sanctum
- Laravel Socialite
- Laravel Telescope
- Sentry Laravel

### Frontend

- React 18
- Inertia.js
- Tailwind CSS 4
- Headless UI
- Vite 5
- Chart.js

### Tooling

- Pest
- Laravel Pint

## Quick Start

### Requirements

- PHP 8.2+
- Composer
- Node.js 18+
- npm
- MariaDB/MySQL-compatible database
- PHP extensions required by `composer.json`, including `bcmath` and `yaml`

### Install

```bash
composer install
npm install
php artisan key:generate
php artisan migrate
php artisan serve
npm run dev
```

If you are developing from WSL on a Windows-mounted workspace, prefer Windows-native `php`, `composer`, `node`, and `npm` binaries.

## Environment

Minimum example:

```env
APP_NAME=YAP
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=yap
DB_USERNAME=root
DB_PASSWORD=

YAP_ADMIN_PANEL_PATH=admin
YAP_UNIT_PRICE=0.02
YAP_RESET_SUBSCRIPTION_PRICE=0.5
YAP_USD_RMB_RATE=7.3
```

Common optional integrations:

```env
GITHUB_CLIENT_ID=
GITHUB_CLIENT_SECRET=
GITHUB_REDIRECT_URI=
GITHUB_WEBHOOK_SECRET=

ALIPAY_APP_ID=
ALIPAY_PRIVATE_KEY=
ALIPAY_PUBLIC_KEY=

STRIPE_KEY=
STRIPE_SECRET=
STRIPE_WEBHOOK_SECRET=

BEPUSDT_APP_ID=
BEPUSDT_API_KEY=
BEPUSDT_SECRET=

SENTRY_LARAVEL_DSN=
```

## Main Routes

### Frontend

- `/`
- `/dashboard`
- `/recharge`
- `/package`
- `/payment`
- `/balance/detail`
- `/customer/service`
- `/profile`
- `/stat`
- `/policy`
- `/tos`
- `/commercial-disclosure`

### Subscription and Payments

- `/clash/{uuid}/yap.yaml`
- `/alipay/*`
- `/bepusdt/*`
- `/stripe/*`

### Admin

- `/{YAP_ADMIN_PANEL_PATH}`

Default admin path is `admin`.

## Admin Access

Filament access is currently restricted in `App\Models\User::canAccessPanel()`.

Current default:

- only the user with `id === 1` can access the admin panel

Review this rule before production use.

## Development Commands

```bash
php artisan test
./vendor/bin/pint
npm run build
```

## Upgrade Status

The project has been upgraded to:

- Filament 5
- Livewire 4
- Tailwind CSS 4

Customer-facing routes and Inertia page structure were kept intact. The main frontend impact is the Tailwind 4 build migration.

Validated locally with:

- `php artisan test`
- `npm run build`

## License

This project is licensed under the MIT License. See `LICENSE` for details.

---

<div align="center">

**Built with Laravel, Inertia, React, and Filament**

</div>
