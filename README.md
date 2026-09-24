# Attendance Workforce

Laravel workforce timekeeping application with employee attendance, administrative corrections, department workspaces, monthly DTR PDFs, attendance reports, and private bulk DTR ZIP downloads.

## Runtime requirements

- PHP 8.4 or newer
- Laravel 13
- Composer 2
- MySQL 8 or a compatible production database
- Node.js and npm for Vite production assets
- PHP extensions required by Laravel and Dompdf, including DOM, fileinfo, mbstring, PDO, PDO MySQL, XML, and zlib
- PHP ext-zip for bulk department DTR archives

The application timezone is `Asia/Manila`. Attendance timestamps and work dates are server-authoritative; the browser clock is display-only.

## Application configuration

Copy `.env.example` to `.env`, configure the application key and MySQL connection, then configure mail delivery for employee invitations. The local default mail driver writes to the application log and is not suitable for production invitation delivery.

The web/PHP process must be able to write to `storage/` and `bootstrap/cache/`. Bulk DTR generation specifically uses the private temporary directory `storage/app/private/dtr-bulk` and removes each archive after constructing the response.

The fixed unpaid DTR break is configured at `workforce.dtr.break_minutes` and defaults to 60 minutes.

## Installation

```bash
composer install
php artisan key:generate
php artisan migrate
npm install
npm run build
```

Do not use the local administrator seeder outside the local environment. Configure `LOCAL_ADMIN_NAME`, `LOCAL_ADMIN_EMAIL`, and `LOCAL_ADMIN_PASSWORD` before running it locally.

## Verification

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact
npm run build
php artisan route:list --except-vendor
php artisan migrate:status
git diff --check
```
