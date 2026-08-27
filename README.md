# Agency HRM — Backend

Laravel 13 API for the Agency Human Resource Management System. See
[`docs/PRD.md`](docs/PRD.md) for the full product spec — this file is just enough to get
running. `docs/architecture.md`, `database.md`, `permissions.md`, and `api.md` are
quick-reference companions to the PRD.

The companion frontend lives in a separate repository:
[`hrms_frontend`](https://github.com/medevjb/hrms_frontend).

## Stack

PHP 8.4 · Laravel 13 · MySQL 9 · Sanctum (Bearer tokens for `/api/v1`) · Fortify
(session auth for the `/system` console) · Pest 5 · Laravel Boost

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate

# create the databases (MySQL, e.g. via DBngin) — see .env for connection details
mysql -e "CREATE DATABASE hrms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "CREATE DATABASE hrms_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

php artisan migrate
npm install && npm run build   # only needed for the /system Inertia console
```

## Running

```bash
php artisan serve       # http://localhost:8000
php artisan test        # Pest, against hrms_test
vendor/bin/pint --dirty # format changed files
vendor/bin/phpstan analyse
```

## What lives where

```text
/api/v1/*   JSON API — everything Next.js talks to. Business rules are decided here,
            never in the frontend (PRD §4).
/system     Inertia + React console, session-authenticated — System Admin/DevOps only
            (PRD §5.1, §79). No HR feature is ever built on this surface.
```
