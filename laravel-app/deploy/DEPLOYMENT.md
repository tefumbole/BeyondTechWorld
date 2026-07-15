# Beyond Enterprise — Deployment & Setup Guide

Deployment-prep reference for the Beyond Enterprise Laravel application (the merged
Beyond public site + admin/POS backend).

> **Note:** This guide is instructions only. Nothing here runs against a live
> server automatically — run each server step yourself when you have VPS access.

---

## 1. Architecture at a glance

| Aspect | Value |
|--------|-------|
| Framework | Laravel 6 (`laravel/framework ^6.2`) |
| PHP | **7.4** (`ea-php74`) — required range `^7.2` |
| Database | MySQL 5.7 / 8.0 |
| Front-end build | **None required** — Tailwind + Alpine + Lucide load from CDN in the Beyond layout |
| Layout | **Single-folder (Hostinger)**: the front controller is the **project-root `index.php`**, not `public/index.php`. Static assets live at the root and under `/public`. |
| Web root | The **project root** (`laravel-app/`), rewriting to `index.php` |
| Queue | `sync` by default (no worker process needed) |
| Scheduler | Active — needs a per-minute cron (see §7) |

Local dev is served with PHP's built-in server + a router:

```bash
php -S 127.0.0.1:8817 -t . server.php
```

---

## 2. Requirements

- PHP 7.4 with extensions: `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `gd`,
  `json`, `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml`, `zip`
- Composer 2.x
- MySQL 5.7+ (or MariaDB 10.3+)
- A web server: **nginx + php-fpm** (see `deploy/nginx.conf.example`) or Apache
  (the root `.htaccess` already handles rewrites on Hostinger/cPanel)

---

## 3. Local development setup

```bash
cd laravel-app

# 1. Dependencies
composer install

# 2. Environment
cp .env.example .env
php artisan key:generate

# 3. Configure DB in .env (local example)
#    DB_DATABASE=beyondtech_laravel
#    DB_USERNAME=beyond
#    DB_PASSWORD=beyond_local

# 4. Create the database + user (MySQL)
#    CREATE DATABASE beyondtech_laravel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
#    CREATE USER 'beyond'@'localhost' IDENTIFIED BY 'beyond_local';
#    GRANT ALL PRIVILEGES ON beyondtech_laravel.* TO 'beyond'@'localhost';

# 5. Import legacy data, then run migrations
mysql -u beyond -p beyondtech_laravel < db-import/mainmarket.sql
php artisan migrate

# 6. Serve
php -S 127.0.0.1:8817 -t . server.php
#    → http://127.0.0.1:8817
```

For faster local testing, `BEYOND_SKIP_OTP=true` (in `.env`) bypasses the
WhatsApp OTP step at login. **Always set this to `false` in production.**

---

## 4. Environment variables

Key groups (full template in `.env.example`):

- **App:** `APP_NAME`, `APP_ENV`, `APP_KEY`, `APP_DEBUG`, `APP_URL`, `APP_VERSION`
- **Database:** `DB_*`
- **WhatsApp (WasenderAPI):** `WHATSAPP_SERVICE=WASENDER`, `WASENDER_API_KEY`,
  `WASENDER_SESSION_ID`, `WASENDER_BASE_URL`, `WHATSAPP_DEFAULT_COUNTRY_CODE`,
  `COMPANY_NAME`
- **Beyond portal:** `BEYOND_SKIP_OTP` (local only)
- **Mail:** `MAIL_*`

---

## 5. Database

- Legacy POS/storefront data lives in `db-import/mainmarket.sql`.
- The Beyond modules add their own migrations (shareholders, training,
  jobs/applications, tasks, HR payroll/payslips, timesheets). Apply with:

```bash
php artisan migrate --force     # --force is required when APP_ENV=production
```

Migrations are idempotent (`Schema::hasTable` guards), so re-running is safe.

---

## 6. File permissions

The web-server user (e.g. `www-data`) must be able to write:

```bash
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# Upload targets used by the app (CVs, signatures, documents)
mkdir -p public/uploads
chmod -R 775 public/uploads public/logo public/images 2>/dev/null || true
```

This app writes uploads under `public/uploads/...` (single-folder layout), so no
`php artisan storage:link` is required for those.

---

## 7. Scheduler (cron) — required

The app schedules reminders and announcement/letter sends every minute. Add ONE
cron entry so Laravel's scheduler runs:

```cron
* * * * * cd /var/www/beyondtechworld && php artisan schedule:run >> /dev/null 2>&1
```

Scheduled jobs: `reminder:cron`, `announcements:send-scheduled`,
`letters:send-scheduled`, `rental:return-reminders`, `bookings:send-reminders`.

---

## 8. Queue

`QUEUE_CONNECTION=sync` — jobs run inline, so **no queue worker is needed**.
If you later switch to `database`/`redis`, run a worker (e.g. via systemd or
`php artisan queue:work`) and create the `jobs`/`failed_jobs` tables.

---

## 9. Web server

### nginx + php-fpm
Use `deploy/nginx.conf.example` as a starting point. It sets the docroot to the
project root, routes everything to `index.php`, **denies** access to `.env`,
`app/`, `config/`, `storage/`, `vendor/`, etc. (critical because the docroot is
the project root, not `/public`), and adds static caching + gzip.

### Apache / Hostinger shared hosting
The root `.htaccess` already handles front-controller rewrites, PHP 7.4 handler,
static caching, and gzip. No extra config needed beyond pointing the domain's
`public_html` at the project files.

---

## 10. Production optimisation

Run after each deploy (the Hostinger script in §11 already does this):

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:clear && php artisan cache:clear && php artisan view:clear
php artisan config:cache
php artisan view:cache
```

> Route caching (`route:cache`) is intentionally **not** used here to stay
> compatible with the existing route definitions.

### Writable storage (critical on VPS)

Never leave `storage/` or `bootstrap/cache` owned by `root`. PHP-FPM (`www-data`)
must own them, or the admin dashboard 500s when Spatie permission cache is written.

**Preferred (VPS):**

```bash
bash tools/deploy-beyondtechworld-laravel.sh
# or after any manual artisan:
chown -R www-data:www-data storage bootstrap/cache
# or run artisan as www-data:
sudo -u www-data php artisan view:clear
```

---

## 11. Existing Hostinger deploy script

`deploy/deploy-site.sh` automates a shared-hosting deploy from the site's
`public_html` (git pull → restore tracked static assets → `composer install
--no-dev` → migrate → cache). It **preserves** `.env`, `storage`, and uploads,
and backs up `.env` before pulling.

```bash
# Run FROM the server, inside the site's public_html:
./deploy/deploy-site.sh beyondtechworld.com
# or
SITE_ROOT=~/domains/beyondtechworld.com/public_html ./deploy/deploy-site.sh
```

> Do not run this locally — it targets a live server. Left for you to run
> manually when deploying.

---

## 12. ⚠️ Git tracking note (action needed before first deploy)

Currently the `laravel-app/` directory is **not committed** to the repository,
and these deploy-critical files are untracked/gitignored:

- `index.php` (root front controller) — **untracked**
- `.htaccess` (root rewrites) — **untracked**
- `server.php` (local dev router) — **gitignored**
- `public/.htaccess` — **untracked**

If you deploy via `git pull`, commit `index.php`, `.htaccess`, and
`public/.htaccess` first, otherwise the site will have no front controller or
rewrite rules on the server. (`server.php` is local-only and can stay ignored.)

---

## 13. Production hardening checklist

- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false`
- [ ] `APP_KEY` set (`php artisan key:generate`)
- [ ] `APP_URL=https://beyondtechworld.com`
- [ ] `BEYOND_SKIP_OTP=false` (or unset)
- [ ] Strong, non-root `DB_USERNAME` / `DB_PASSWORD`
- [ ] HTTPS enabled (certbot); force redirect 80→443
- [ ] `SESSION_SECURE_COOKIE=true` once on HTTPS
- [ ] `storage/` and `bootstrap/cache/` writable by web user
- [ ] Scheduler cron installed (§7)
- [ ] `.env`, `vendor/`, `storage/`, `app/` NOT web-accessible (verify with the
      nginx rules or `.htaccess`)
- [ ] Real `WASENDER_API_KEY` / `WASENDER_SESSION_ID` configured
- [ ] Config + view caches built (§10)
