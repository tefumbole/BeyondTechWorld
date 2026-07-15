# Restore Point: ABT_ERP_V.2.2.15

**Date:** 2026-07-15  
**Git tag:** `ABT_ERP_V.2.2.15`  
**Login version label:** `2.2.15`  
**Commit:** `dcbd6b4` (manifest commit may be tip after this file is added)

## Snapshot includes

- Timesheet module (Employee + Admin)
  - Create Activity, Fill Time Sheet, Working Week
  - Categories, TimeSheet Report, Overtime Report, Manage All
  - Category-colored activity icons and working-week Summary UI
- Laravel production deploy safety
  - `tools/deploy-beyondtechworld-laravel.sh` (www-data storage ownership)
  - Cursor rule for permission-safe Laravel feature deploys
- Prior: Courses manager, Announcements, Events, gallery/CMS, Task Manager UI

## Restore code to this point

```bash
git fetch --tags
git checkout ABT_ERP_V.2.2.15
# or on a branch:
git checkout -b restore-abt-v2.2.15 ABT_ERP_V.2.2.15
```

## Redeploy production

```bash
ssh myvps 'cd /var/www/beyondtechworld && git fetch --tags && git checkout ABT_ERP_V.2.2.15 && bash tools/deploy-beyondtechworld-laravel.sh --migrate-all'
```

After a restore, return `main` to latest intentionally — do not leave production on a detached tag unless planned.

## Create the next restore point

1. Bump `APP_VERSION` in `src/constants/appVersion.js` (or let pre-push hook increment)
2. Add a new file under `restore-points/`
3. Run: `bash tools/create-restore-point.sh`
4. Push: `git push origin ABT_ERP_V.x.y.z`
