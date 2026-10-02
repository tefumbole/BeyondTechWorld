# Beyond Cloud Phase 1C backup

Taken before any ownership column was added. This file contains no passwords and no API keys.

| Item | Value |
| --- | --- |
| Database | `beyondtechworld_laravel` |
| Timestamp | 2026-10-02 11:40:23 UTC |
| Server path | `/var/backups/beyondtechworld/20261002-114023-phase1c/` |
| Local copy | `BeyondTechWorld-backups/20261002-114023-phase1c/` (outside the git repo) |
| Dump | `database.sql.gz` (9.6 MB) |
| SHA-256 | `d458b6e2d1e729d84238b2e8a5cbf02001f914f5fd00652254c720efdbe1b835` |
| gzip check | passed |
| Application commit | `5acc7b8ab5e1c36944cbcc23a4cbce2ad1750e96` |
| Migration level | `2026_10_02_120000_add_account_id_to_deposits` |

The dump was taken from the live database with a read-only `mysqldump`. The live schema was not changed. Directory mode on the server is `700`. The dump file mode is `600`.

## Restore

1. Create an empty database. Do not restore over `beyondtechworld_laravel` unless that is the approved recovery target.
2. Load `database.sql.gz` into that empty database.
3. Confirm `products` is 471 rows and `products.cloud_tenant_id` does not exist.
4. Confirm `cloud_tenants` exists and has 0 rows.

This restore was performed on a separate database, `beyond_cloud_rehearsal`, and then that database was dropped. The backup files were kept.
