# Beyond Enterprise

The Beyond Enterprise web platform — a single Laravel 6 application that serves
both the **public Beyond Enterprise website** (Blade + Tailwind/Alpine) and the
**admin / POS backend**, merged from the legacy mainmarket system and rebranded.

## What's inside

**Public site & portals** (`resources/views/beyond/`):

- Marketing pages — Home, About, Services, Projects, Contact, Events
- Public auth — login, WhatsApp OTP, forgot password, profile
- **Training** — courses, registration, student dashboard + progress/feedback
- **Apply Now** — job board, applications with CV upload, applicant dashboard
- **Shareholders** — terms, registration with e-signature, agreement verification
- **Tasks** — assignments, invites, signature-based acceptance, progress
- **Payslip verification** — `/verify/payslip/{code}`
- **Staff timesheet** — self-service hour logging + monthly summary

**Admin / POS** (`/admin`, legacy controllers) — products, sales, purchases,
HR/payroll, bookings, letters, announcements, and the storefront at `/store`.

## Tech stack

- Laravel 6 · PHP 7.4 · MySQL
- Tailwind CSS + Alpine.js + Lucide (via CDN — no front-end build step)
- WhatsApp messaging via WasenderAPI

## Quick start

```bash
cd laravel-app
composer install
cp .env.example .env
php artisan key:generate
# configure DB_* in .env, then:
mysql -u <user> -p <db> < db-import/mainmarket.sql
php artisan migrate
php -S 127.0.0.1:8817 -t . server.php   # http://127.0.0.1:8817
```

Test portal account (local): `portal@beyondtechworld.com` / `beyond123`.
Set `BEYOND_SKIP_OTP=true` in `.env` to bypass OTP during local testing.

## Deployment

See **[`deploy/DEPLOYMENT.md`](deploy/DEPLOYMENT.md)** for the full setup,
web-server (nginx sample in `deploy/nginx.conf.example`), cron, and production
hardening guide.
