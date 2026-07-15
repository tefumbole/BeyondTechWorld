# Beyond Enterprise (BeyondTechWorld)

Web application for **Beyond Enterprise**, deployed at [beyondtechworld.com](https://beyondtechworld.com).

Alpha Bridge runs separately at [alpha-bridge.net](https://alpha-bridge.net) on the same VPS (port 3003).

## Local development

```bash
npm run dev:local
```

## VPS ports

| Site | Domain | API port | PM2 process | Web root |
|------|--------|----------|-------------|----------|
| Alpha Bridge | alpha-bridge.net | 3003 | alphabridge-api | /var/www/alphabridge |
| Beyond Enterprise | beyondtechworld.com | 3004 | beyondtechworld-api | /var/www/beyondtechworld |

## Deploy

```bash
# Alpha Bridge (Node API)
ssh myvps "cd /var/www/alphabridge && git pull && bash tools/deploy-alphabridge-vps.sh"

# Beyond Enterprise — live Laravel site (use this for feature deploys)
ssh myvps "cd /var/www/beyondtechworld && git pull && bash tools/deploy-beyondtechworld-laravel.sh"
# optional: --migrate-all   or   --migrate-path=database/migrations/….php

# Beyond Enterprise — Node API stack (port 3004 only; not the live Laravel admin)
ssh myvps "cd /var/www/beyondtechworld && git pull && bash tools/deploy-beyondtechworld-vps.sh"
```

The Laravel script always restores `www-data` ownership on `storage/` and
`bootstrap/cache` so admin does not 500 after root-run Artisan.

## Beyond Enterprise database (separate from Alpha Bridge)

Create in Hostinger hPanel → Databases → MySQL:

- Database: `beyondtechworld` → `u152889834_beyondtechworld`
- User: `u152889834_beyondtechworld` with full privileges
- Remote MySQL: allow VPS IP `187.124.2.238`

Then on VPS: copy `apps/api/.env.beyondtechworld.example` → `apps/api/.env` and run `bash tools/setup-beyondtechworld-db.sh`.

## Branding

- **Beyond Enterprise**: logo `/branding/beyond-logo.png`, hero `/branding/beyond-hero.png`
- Override via `.env`: `VITE_COMPANY_NAME`, `VITE_LOGO_URL`, `VITE_HERO_IMAGE_URL`, `VITE_ADMIN_PHONE_NUMBER`
