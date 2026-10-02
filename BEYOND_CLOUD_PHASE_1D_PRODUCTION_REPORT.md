# Beyond Cloud — Phase 1D production report

Status: **PHASE 1D PRODUCTION VALIDATION PASSED**

Rollback required: **NO**

Phase 1E was not started. Public company signup stays closed. Ownership columns stay nullable.

## Backup

| Item | Value |
| --- | --- |
| UTC timestamp | 2026-10-02 17:48:15 UTC |
| Database | `beyondtechworld_laravel` |
| Path | `/var/backups/beyondtechworld/20261002-174815-phase1d-prod/database.sql.gz` |
| Size | 7.6 MB |
| SHA-256 | `7a002af8538714a6b28de03ef0db279d0be576d9055eeb8efc842107094feb6b` |
| Git commit at backup | `f170af7c4fa459d6d0620f550d43a4197f2e6419` |
| Target commit | `50c81a9d03a6ec1184d65d9a5bc306a18d46e445` |
| Migration level at backup | through `2026_10_02_144000_add_nullable_cloud_tenant_to_whatsapp` (batch 214) |

Restore check: the gzip was loaded into a temporary database and then dropped. Restored counts were products 471, sales 30, customers 744. `products.cloud_tenant_id` was present. `cloud_whatsapp_connections` was absent, which matches the pre-1D schema. The earlier Phase 1C backup was not used as this restore point.

## Commits

| | Commit |
| --- | --- |
| Production before deploy | `f170af7c4fa459d6d0620f550d43a4197f2e6419` |
| Deployed HEAD | `50c81a9d03a6ec1184d65d9a5bc306a18d46e445` |

Production `HEAD` after deploy is `50c81a9d03a6ec1184d65d9a5bc306a18d46e445`. Unrelated working-tree files on the server were not committed or reset.

## Migrations

Reviewed between `f170af7` and `50c81a9`. One new migration:

`2026_10_02_180000_add_cloud_whatsapp_connection_and_tenant_phone.php`

It created `cloud_whatsapp_connections` and replaced the global unique index on `whatsapp_contacts.normalized_phone` with `whatsapp_contacts_tenant_phone_unique` on `(cloud_tenant_id, normalized_phone)`.

That migration was the only one run. Confirmed on the live database:

- unique index `whatsapp_contacts_tenant_phone_unique` columns `cloud_tenant_id,normalized_phone`
- the old `whatsapp_contacts_normalized_phone_unique` index is gone
- `cloud_tenant_id` remains nullable on products, customers, sales, quotations, payments, bookings, WhatsApp contacts, and WhatsApp conversations

Before the index change, every existing WhatsApp contact and conversation had a valid BeyondTechWorld owner. See the ownership section.

## Ownership audit before deployment

The first read, before any write in this phase, was not clean:

| Table | Unowned |
| --- | --- |
| products, categories, brands, units, warehouses, customers, suppliers, sales, quotations, payments, bookings | 0 |
| whatsapp_contacts | 834 |
| whatsapp_conversations | 5 |

Invalid tenant references: 0. Cross-tenant relationship mismatches: 0. Other-tenant rows: 0. Tenant count: 1, and that tenant is INTERNAL.

The 834 contacts and 5 conversations were created after the Phase 1C backfill, between 2026-10-02 13:43 and 18:49 server time, while new WhatsApp inserts still had no ownership hook. Twenty messages sit on those five conversations and stay derived through the conversation. There is no second company.

Dry-run of the already deployed command `cloud:rehearse-beyond-migration`:

- contacts: total 2329, unowned 834, would_assign 834, ambiguous 0
- conversations: total 179, unowned 5, would_assign 5, ambiguous 0
- every other direct table: unowned 0, ambiguous 0
- relationship orphans: 0

It was then executed with `--execute` and `--confirm-production=APPLY-BEYONDTECHWORLD-OWNERSHIP`. A second dry-run showed unowned 0 and ambiguous 0 on every direct table. Deployment started only after that.

## Production row counts before deployment

Captured after the ownership assignment and before `50c81a9` went live:

| Table | Count |
| --- | --- |
| products | 471 |
| customers | 744 |
| sales | 30 |
| quotations | 25 |
| payments | 30 |
| bookings | 64 |
| whatsapp_contacts | 2329 |
| whatsapp_conversations | 179 |
| whatsapp_messages | 2414 |

These counts are higher than the Phase 1C snapshot because live WhatsApp traffic continued. They were not forced back to the older numbers.

## CloudTenant verification

The existing tenant was not recreated.

| Field | Value |
| --- | --- |
| id | 1 |
| uuid | `115d4fc7-1024-4fba-bcc6-53898a2ae4a3` |
| name | BeyondTechWorld |
| type | INTERNAL |
| status | ACTIVE |
| slug | beyondtechworld |
| currency | XAF |
| timezone | Africa/Douala |

Memberships: 1 active OWNER (user role_id 2) and 4 active STAFF (user role_id 15). Platform Admin role_id 1 has no membership. Enabled internal entitlements: WHATSAPP_HUB, SALES_INVOICES, RENTALS. Subscriptions: 0. Customer tenants: 0.

## Deployment window

The site was put in maintenance, `beyondtechworld-whatsapp-queue` was stopped without deleting queued jobs, then the normal deploy script pulled `50c81a9` and ran only the migration above. Config, route, view, and application caches were cleared by that script. PHP 7.4-FPM was reloaded. The pre-existing letterhead warning (“Header is not the Beyond branding file”) appeared again and is not an ownership failure.

After validation the site was brought back up. The queue worker was restarted and is online as pid 3176223, command `php artisan queue:work database --queue=whatsapp,default --sleep=1 --tries=3 --timeout=90`, cwd `/var/www/beyondtechworld/laravel-app`.

## Fallback validation

| Check | Result |
| --- | --- |
| No context, product query | 0 rows (fail closed) |
| No context, product create | denied (`MissingCloudTenantException`) |
| Legacy resolver | only slug `beyondtechworld`, type INTERNAL, id 1 |
| Legacy context, product query | 471 rows |
| Public `/rentals` | HTTP 200 |
| `cloud_tenant_id = 999` supplied on create | overwritten; saved row is tenant 1 |
| Phone-only webhook payload | resolved to BeyondTechWorld because there is exactly one ACTIVE connection, not because the phone was used as a tenant key |
| Session payload | resolved to BeyondTechWorld |

`config('cloud.public_onboarding')` is off. `legacy_internal_context` and `isolate_queries` are on.

A second production company was not created. The two-connection case, where a phone-only payload must return no tenant, remains the sqlite result from `50c81a9` and was not reproduced with a fake live company.

## Platform Admin

The active role_id 1 user has no CloudTenant membership. `CloudTenantResolver::forUser` returns only the INTERNAL `beyondtechworld` tenant, the same legacy rule used for a guest. Role 1 does not receive a scope over every tenant. There is no customer tenant to cross into.

## Live writes

All of these were created under the BeyondTechWorld context, then removed with application behavior. No production id was edited by hand.

| Record | Ownership | Cleanup |
| --- | --- | --- |
| Product id 493, name PHASE 1D OWNERSHIP CHECK | `cloud_tenant_id` 1, not null | deleted; leftover 0; products back to 471 |
| Customer id 745 | tenant 1, not null | deleted; leftover 0; customers back to 744 |
| Quotation id 29 | tenant 1, same tenant as its customer | deleted; leftover 0; quotations back to 25 |
| Booking id 66 | tenant 1 | deleted; leftover 0; bookings back to 64 |
| Sale | tenant 1 inside a transaction, matching the test customer | rolled back; leftover sale 0; sales stayed 30 |
| WhatsApp contact `237600009991` and its conversation | both tenant 1 | deleted; leftover 0; contacts 2329 and conversations 179 |

No customer was messaged. The sale was not committed, so no financial record was left behind.

## WhatsApp provider resolution

`cloud_whatsapp_connections` id 1:

- `cloud_tenant_id` 1
- provider `wasender`
- status ACTIVE
- credentials reference is an env key name; the API secret is not stored on the row

Inbound processing path checked in process: session id on the payload resolves to BeyondTechWorld; a phone-only payload uses that single connection. New contact and conversation rows receive tenant 1.

## MAI

Under BeyondTechWorld context, `search_rental_products` for “speaker” returned 8 products. Sample day rates:

- BEHRINGER SINGLE BASS SPEAKER PASSIVE — 0
- COMBO BASS SPEAKER HARTKE — 0
- DOUBLE BASS SPEAKER BEYOND — 20000
- MACKIE LOW SPEAKER — 10000
- Mid speaker (BEHRINGER B1520 PRO) — 10000

The tool argument `cloud_tenant_id = 2` was ignored. With context cleared, the same tool returned `tenant_context_required` and no products.

A separate general question (“Reply with exactly: two plus two is four.”) was sent through the configured OpenAI provider. It answered and included “four”. Tenant isolation did not break ordinary conversation.

## IDOR

- `Product::find(99999999)` under BeyondTechWorld context: not found
- product count with context cleared: 0
- unauthenticated `GET /admin/whatsapp/conversations/99999999`: HTTP 302 to login, not a record

No real customer row was opened or changed for this check.

## Queue

`CloudTenantContextRunner` was executed with BeyondTechWorld and then threw. After the exception, context was clear.

The worker loaded `50c81a9` after restart. Contact-name jobs that were waiting ran and requeued group-resolution jobs. No `MissingCloudTenant` or `CrossTenant` error was logged.

One failed job was recorded at 2026-10-02 18:52:17 server time: `ResolveWhatsAppContactNamesJob` exceeded max attempts because the worker was stopped while that job was reserved. The same job class already had this failure pattern on 2026-10-01. It is not a missing-tenant failure. Failed-job count from this window: 1. The worker stayed online afterward.

## Ownership audit after

`php artisan cloud:audit-ownership` after the writes, cleanup, and worker activity:

- unowned_total = 0
- invalid_tenant_total = 0
- cross_tenant_links = 0
- contacts 2329, conversations 179, products 471, customers 744, sales 30, quotations 25, bookings 64

New NULL ownership: **0**.

## NULL ownership decision

Database NOT NULL was **not** applied.

These columns are populated for every current row and are candidates for a later NOT NULL decision, after production has been watched:

products, categories, brands, units, warehouses, customers, suppliers, sales, quotations, payments, bookings, whatsapp_contacts, whatsapp_conversations.

`whatsapp_messages` stays derived through `whatsapp_conversations` and has no ownership column.

## Regression

| Check | Result |
| --- | --- |
| `/` | HTTP 200 |
| `/login` | HTTP 200 |
| `/dashboard`, `/products`, `/sales`, `/quotations` | HTTP 302 to login |
| `/admin/whatsapp` | HTTP 302 to login |
| `/rentals` | HTTP 200 |
| `/subscriptions` | HTTP 200 |
| Messaging plan | 5,000.00 XAF |
| Trial | 24 HOUR on every plan |
| WhatsApp Hub / Sales & Invoices / Rentals prices | 10000 / 5000 / 5000 XAF, unchanged |
| INTERNAL subscriptions | 0 |
| Signed quotation CC method | `sendQuotationPdfCopyToCc` still present |
| Quotation notes after footer | `.inv-note-after` still in the PDF view |
| Blank sale note on convert | `name="sale_note"` textarea is empty |
| `/cloud/register` | HTTP 200 and shows “Company signup is not open yet.” |
| Customer tenants created | 0 |

Authenticated clicks inside the ERP were not performed with a staff password. The write tests above used the application models with the server tenant context, which is the same context those screens receive after login.

## Production errors

No missing-tenant or cross-tenant errors after deploy. The only new queue failure is the max-attempts name job from the worker stop, described above. Imagick is still absent, so quotation QR WhatsApp attach can still skip; that predates this deploy.

## Rollback

**NO.** Products, sales, quotations, bookings, and WhatsApp rows remained countable and owned. The public catalogue query under the BeyondTechWorld fallback returned 471 products. New writes received tenant 1. Context clears after a failed job.
