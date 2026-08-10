# Restore Point: BCL_ERP_V2.8.8

**Date:** 2026-08-10  
**Git tag:** `BCL_ERP_V2.8.8`  
**Login version label:** `BCL_ERP_V2.8.8` / `2.8.8`  
**Commit:** see `git rev-list -n1 BCL_ERP_V2.8.8`

## Snapshot includes

- Internship offer portal (new applications)
  - Working Week required on Apply
  - Multi-step offer link: accept T&Cs → password → Working Week → sign → hired
  - Legacy applications keep short agreement flow (`offer_flow_version = 0`)
  - Program **Max students** + admin reassignment with seat capacity
  - Interns list: “Saved on application” Working Week state; tab search no longer hides rows
- General Setting fixes
  - Time Zone persists to `.env` (`APP_TIMEZONE`)
  - Invoice Format selection restored correctly after save
- Quotations
  - Client signature → branded PDF with **staff + client** signatures via WhatsApp
  - Reject → no PDF to client
  - Rich-text Notes: pasted Word/Docs formatting (headings, bold, lists, tables) preserved
- Prior stack: Job Board, Internships / Task Manager, Timesheets, Announcements, WhatsApp (Wasender), Booking, Events, etc.

## Restore code to this point

```bash
git fetch --tags
git checkout BCL_ERP_V2.8.8
# or on a branch:
git checkout -b restore-bcl-v2.8.8 BCL_ERP_V2.8.8
```

## Redeploy production

```bash
ssh myvps 'cd /var/www/beyondtechworld && git fetch --tags && git checkout BCL_ERP_V2.8.8 && bash tools/deploy-beyondtechworld-laravel.sh --migrate-all'
```

After a restore, return `main` to latest intentionally — do not leave production on a detached tag unless planned.

## Database / file backup

Production backup created with this restore point (see `backups/` and VPS copies):

| File | Location |
|------|----------|
| SQL dump (gzip) | `backups/production-BCL_ERP_V2.8.8-20260810-151835.sql.gz` (~1.2 MB) |
| Uploads/logo tar | `backups/production-BCL_ERP_V2.8.8-20260810-151835-files.tar.gz` (~9.0 MB) |
| Manifest | `backups/production-BCL_ERP_V2.8.8-20260810-151835.manifest.txt` |

VPS copies: `/var/www/beyondtechworld/backups/production-BCL_ERP_V2.8.8-20260810-151835.*`

## Create the next restore point

1. Ensure `APP_VERSION` in `src/constants/appVersion.js` (and `laravel-app/VERSION`) matches the desired tag
2. Add a new file under `restore-points/`
3. Run: `bash tools/create-restore-point.sh` (or `npm run restore-point`)
4. Push: `git push origin BCL_ERP_Vx.y.z` and `git push origin main`
