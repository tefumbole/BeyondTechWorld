# Beyond Cloud Phase 1C production report

**PRODUCTION OWNERSHIP MIGRATION PASSED**

Global tenant scopes are still off. `whatsapp_contacts.normalized_phone` is still globally unique. Public company onboarding was not opened. Phase 1D was not started.

## Backup

| Item | Value |
| --- | --- |
| Database | `beyondtechworld_laravel` |
| UTC timestamp | 2026-10-02 12:19:25 UTC |
| Git commit at backup | `21a726a1515f7c8ba1c779bd90fcf99babd5162e` |
| SHA-256 | `ebe357d6f5dcaaea746e1bc4238ec54a156463eee256dbe335bdb598c03cd26e` |
| Server path | `/var/backups/beyondtechworld/20261002-121925-phase1c-prod/database.sql.gz` |

The dump was restored into a temporary database before the write, checked (471 products, no `cloud_tenant_id`, 0 tenants), and that database was dropped. The backup file was still present and gzip-valid immediately before execution.

## Commit

Immediately before the write, production HEAD was `21a726a`. The five ownership migration files matched the rehearsal checksums. No `2026_10_02_14*` migration had been applied. Products had no `cloud_tenant_id`. `cloud_tenants` was 0.

The approved confirmation guard was then deployed as `2b1e298`. Bare `--execute` still refused. The backfill ran only with `--execute --confirm-production=APPLY-BEYONDTECHWORLD-OWNERSHIP`.

## Schema applied

| Migration | Result |
| --- | --- |
| `2026_10_02_140000_create_cloud_internal_entitlements` | applied |
| `2026_10_02_141000_add_nullable_cloud_tenant_to_catalog` | applied |
| `2026_10_02_142000_add_nullable_cloud_tenant_to_sales` | applied |
| `2026_10_02_143000_add_nullable_cloud_tenant_to_rentals` | applied |
| `2026_10_02_144000_add_nullable_cloud_tenant_to_whatsapp` | applied |

`whatsapp_messages` did not receive `cloud_tenant_id`. Ownership stays on the conversation. The phone unique index `whatsapp_contacts_normalized_phone_unique` is unchanged.

The only `addGlobalScope` in application code is the pre-existing booking scope `pending_booking_requests`.

## BeyondTechWorld CloudTenant

| Field | Value |
| --- | --- |
| id | 1 |
| uuid | `115d4fc7-1024-4fba-bcc6-53898a2ae4a3` |
| name | BeyondTechWorld |
| slug | beyondtechworld |
| type | INTERNAL |
| status | ACTIVE |
| system_name | Beyond Tech World |
| legal_name | Beyond Enterprise |
| currency | XAF |
| timezone | Africa/Douala |

A second `cloud:create-internal-tenant` reused this same row. Tenant count is 1. Subscriptions for this company: 0.

`general_settings.currency` is still `003 RWF`. The CloudTenant currency was left as XAF. That settings row was not changed.

## Memberships

5 memberships. `users.role_id` was not changed.

| Membership | Count | user role_id |
| --- | --- | --- |
| OWNER | 1 | 2 |
| STAFF | 4 | 15 |
| Platform Admin (role 1) | 0 memberships | unchanged |

## Entitlements

Enabled, with no trial and no payment:

- WHATSAPP_HUB
- SALES_INVOICES
- RENTALS

## Before and after row counts

Counts were taken again after maintenance started, and they matched after the backfill. WhatsApp was a few rows higher than the readiness note because live traffic arrived before the pause. Those rows were unowned, not ambiguous, and were included.

| Table | Before | After | Owned by BeyondTechWorld | Unowned | Ambiguous |
| --- | --- | --- | --- | --- | --- |
| products | 471 | 471 | 471 | 0 | 0 |
| categories | 32 | 32 | 32 | 0 | 0 |
| brands | 35 | 35 | 35 | 0 | 0 |
| units | 18 | 18 | 18 | 0 | 0 |
| warehouses | 2 | 2 | 2 | 0 | 0 |
| customers | 744 | 744 | 744 | 0 | 0 |
| suppliers | 1 | 1 | 1 | 0 | 0 |
| sales | 30 | 30 | 30 | 0 | 0 |
| quotations | 25 | 25 | 25 | 0 | 0 |
| payments | 30 | 30 | 30 | 0 | 0 |
| bookings | 64 | 64 | 64 | 0 | 0 |
| whatsapp_contacts | 1495 | 1495 | 1495 | 0 | 0 |
| whatsapp_conversations | 174 | 174 | 174 | 0 | 0 |
| product_sales | 66 | 66 | derived | n/a | 0 |
| product_quotation | 63 | 63 | derived | n/a | 0 |
| booking_products | 61 | 61 | derived | n/a | 0 |
| whatsapp_messages | 2341 | 2341 | derived, no column | n/a | 0 |

## Orphans

| Child | Parent | Orphans |
| --- | --- | --- |
| sales | customers | 0 |
| quotations | customers | 0 |
| bookings | customers | 0 |
| product_sales | sales | 0 |
| product_quotation | quotations | 0 |
| booking_products | bookings | 0 |
| whatsapp_messages | whatsapp_conversations | 0 |

## Regression

| Check | Result |
| --- | --- |
| Home | HTTP 200 |
| Login | HTTP 200 |
| Products | HTTP 302 to login (auth still required) |
| Subscriptions | HTTP 200 |
| Messaging plan | 5,000 XAF, trial 24 hours |
| Other plans | WhatsApp Hub 10,000; Sales & Invoices 5,000; Rentals 5,000; all 24 hours |
| Signed quotation CC method | still present |
| Quotation notes after footer | still present |
| Quotation to invoice sale note | still blank |
| Property tenancy table | `tenancies` |
| Cloud tenant table | `cloud_tenants` |

Authenticated dashboard, sales, invoice, rental, and event screens were not clicked with a staff password. The application is out of maintenance and those routes are served by the same release.

## MAI

Production product search for "speaker" returned 8 Beyond catalogue rows, including DOUBLE BASS SPEAKER BEYOND at day rate 20000, MACKIE LOW SPEAKER at 10000, NEXO MID 15IN SPEAKER at 12000, and SPEAKER BEHRINGER SINGLE LOW ACTIVE at 15000. Some speaker rows still have a day rate of 0 in `rent_price_per_day`. The search did not fail because ownership was added. No global product scope is on.

## WhatsApp

No test message was sent to a customer. After the queue was resumed it processed live `ProcessWasenderWebhook` jobs and a `ProcessAssistantTurn` job. Failed-job count stayed at 12, all dated 2026-10-01. No new conversation or contact was inserted during that check, so unowned WhatsApp rows stayed at 0.

## Queue

The WhatsApp worker was stopped during the write and started again afterward. It is online. Delayed jobs were left in the `jobs` table and were not deleted. New webhook jobs were processed after resume.

## Warning: new rows are not auto-owned

Existing rows are owned. New products, sales, bookings, contacts, and conversations can still be inserted with `cloud_tenant_id` NULL. Nothing in this phase sets that column on create, and there is no global scope. That boundary is Phase 1D. Do not connect an external company until new writes are forced into a CloudTenant.

`whatsapp_messages` still follow the conversation and have no ownership column of their own.

## Rollback

Not required.

## Stop

Phase 1D was not started. Global scopes stay off. Phone uniqueness was not changed. No external tenant was created.
