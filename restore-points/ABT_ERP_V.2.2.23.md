# Restore Point: ABT_ERP_V.2.2.23

**Date:** 2026-07-15  
**Git tag:** `ABT_ERP_V.2.2.23`  
**Login version label:** `2.2.23`  
**Commit:** see `git rev-list -n1 ABT_ERP_V.2.2.23`

## Snapshot includes

- Job Board / Apply Now workflow
  - Jobs vs Internships (separate public sections; internships first when no jobs)
  - Live deadline countdown on Apply Now
  - Clone posting; Add Job / Add Internship
  - Application tabs: All Applications, Awaiting Approval, Selected, Rejected
  - WhatsApp notifications at under review / selected / rejected / agreement signed
  - Single WhatsApp number on applications
  - Internship docs: Student ID, Internship Letter, Selfie — Attach or live camera snap
  - Employment / internship agreement signing after selection
- Site settings logo upload fix (`public/logo`) + public SiteBrand logo
- Logged-in public header profile dropdown (Admin Dashboard / Home / Logout)
- Prior: Timesheets, Courses, Announcements, Events, gallery/CMS, Task Manager

## Restore code to this point

```bash
git fetch --tags
git checkout ABT_ERP_V.2.2.23
# or on a branch:
git checkout -b restore-abt-v2.2.23 ABT_ERP_V.2.2.23
```

## Redeploy production

```bash
ssh myvps 'cd /var/www/beyondtechworld && git fetch --tags && git checkout ABT_ERP_V.2.2.23 && bash tools/deploy-beyondtechworld-laravel.sh --migrate-all'
```

After a restore, return `main` to latest intentionally — do not leave production on a detached tag unless planned.

## Database / file backup

Production backup created with this restore point:

| File | Location |
|------|----------|
| SQL dump (~867 KB) | `backups/production-ABT_ERP_V.2.2.23-20260715-215549.sql` (also on VPS) |
| Uploads/logo tar (~19 MB) | `backups/production-files-ABT_ERP_V.2.2.23-20260715-215549.tar.gz` |
| Manifest | `backups/production-ABT_ERP_V.2.2.23-20260715-215549.manifest.txt` |

VPS copies: `/var/www/beyondtechworld/backups/production-ABT_ERP_V.2.2.23-20260715-215549.*`

## Create the next restore point

1. Bump `APP_VERSION` in `src/constants/appVersion.js` (and `laravel-app/VERSION`)
2. Add a new file under `restore-points/`
3. Run: `bash tools/create-restore-point.sh`
4. Push: `git push origin ABT_ERP_V.x.y.z`
