# Alpha Bridge / Beyond Enterprise ERP restore points

Each restore point is a **git tag** plus a short manifest in this folder.

| Version | Tag | Notes |
|---------|-----|-------|
| ABT_ERP_V.2.2.15 | `ABT_ERP_V.2.2.15` | Timesheets (Employee + Admin), Laravel deploy script (storage perms) |
| ABT_ERP_V.2.2.0 | `ABT_ERP_V.2.2.0` | Events module phases 1–8, gallery/CMS |
| ABT_ERP_V.1.1.2 | `ABT_ERP_V.1.1.2` | Custom role permissions, signed agreements, version auto-bump |
| ABT_ERP_V.1.1.1 | `ABT_ERP_V.1.1.1` | WhatsApp queue, i18n FR/EN, signed agreements, RBAC |

Current version is defined in `src/constants/appVersion.js` and shown on the login page.
Enable auto bump on push: `bash tools/setup-git-hooks.sh`

Create a restore point: `npm run restore-point` then `git push origin <tag>`.
