# Beyond Cloud Phase 1C report

INTERNAL tenant and ownership rehearsal. Public onboarding was not opened. Global tenant scopes were not enabled. The live ownership backfill was not run.

**PRODUCTION MIGRATION READY: NO**

The rehearsal succeeded. Live production still needs an explicit approval before any ownership column or backfill is applied there. Direct-id pages and MAI still read the whole installation, which is required until request context is proven.

## 1. Phase 1B verification

Live database `beyondtechworld_laravel` at commit `5acc7b8ab5e1c36944cbcc23a4cbce2ad1750e96`:

| Table | Present |
| --- | --- |
| cloud_tenants | yes |
| cloud_tenant_memberships | yes |
| cloud_modules | yes |
| cloud_plans | yes |
| cloud_subscriptions | yes |
| cloud_tenant_settings | yes |

Models present: `CloudTenant`, `CloudTenantMembership`, `CloudModule`, `CloudPlan`, `CloudSubscription`, `CloudTenantSetting`.

Live plans:

| Plan | Price | Trial |
| --- | --- | --- |
| WhatsApp Hub | 10,000 XAF / month | 24 hours |
| Sales & Invoices | 5,000 XAF / month | 24 hours |
| Rentals | 5,000 XAF / month | 24 hours |

The original Phase 1B draft said a one-month trial. That was changed, on purpose, to 24 hours. This is not a Phase 1B regression and was not reverted.

A Messaging plan at 5,000 XAF exists only in the uncommitted local catalog. It is not on production and it is not an INTERNAL entitlement.

Operational tables checked on production have no `cloud_tenant_id`: products (471), categories, brands, units, warehouses, customers, suppliers, sales, quotations, payments, bookings, whatsapp_contacts, whatsapp_conversations, users, billers, general_settings.

No Cloud global scope exists. The only `addGlobalScope` under `app/` is the pre-existing booking scope `pending_booking_requests`.

Tests, before Phase 1C code, all passing:

| Suite | Tests | Assertions | Failures |
| --- | --- | --- | --- |
| CloudPlatformFoundationTest | 7 | 90 | 0 |
| CloudPortalTest | 3 | 43 | 0 |
| AccountDepositTest | 1 | 7 | 0 |

Foundation was run again after the Phase 1C code: 7 tests, 91 assertions, 0 failures. Internal-tenant tests: 3 tests, 36 assertions, 0 failures.

## 2. Backup / restore point

See `BEYOND_CLOUD_PHASE_1C_BACKUP.md`.

Database `beyondtechworld_laravel`, 2026-10-02 11:40:23 UTC, commit `5acc7b8ab5e1c36944cbcc23a4cbce2ad1750e96`, last migration `2026_10_02_120000_add_account_id_to_deposits`. SHA-256 `d458b6e2d1e729d84238b2e8a5cbf02001f914f5fd00652254c720efdbe1b835`.

## 3. INTERNAL CloudTenant architecture

`php artisan cloud:create-internal-tenant` finds the company by slug `beyondtechworld`. If that slug is missing and exactly one INTERNAL row exists, it reuses that row. A second run prints `Reused` and does not insert another BeyondTechWorld.

Type `INTERNAL` does not start a trial. `CloudPortalService::startTrial` throws for an INTERNAL company. No subscription row and no invoice is created.

Entitlement is `cloud_internal_entitlements`: one enabled row per module. `CloudInternalEntitlementPolicy` returns true only for an INTERNAL tenant with an enabled row. It does not read `trial_ends_at`.

Modules enabled for BeyondTechWorld:

- WHATSAPP_HUB
- SALES_INVOICES
- RENTALS

MESSAGING is not included.

A setting `entitlement_policy = platform_controlled_modules` is stored on the company. It is not a secret.

## 4. BeyondTechWorld CloudTenant record

Created only on the rehearsal database (then removed by restore):

| Field | Value | Source |
| --- | --- | --- |
| name | BeyondTechWorld | Phase 1C name |
| slug | beyondtechworld | stable id |
| type | INTERNAL | Phase 1C |
| status | ACTIVE | Phase 1C |
| currency | XAF | Phase 1C and `cloud_plans` |
| timezone | Africa/Douala | Phase 1C. `general_settings` has no timezone. |
| system_name | Beyond Tech World | `general_settings.site_title` |
| legal_name | Beyond Enterprise | default biller `company_name` |
| email | info@beyondcompanyltd.com | default biller |
| phone | 237675321739 | default biller |
| address | Mile Six, Nkwen | default biller |
| city | Bamenda | default biller |
| country | empty | the biller has no country; none was invented |

Conflicts, source chosen explicitly:

- Site title is "Beyond Tech World". The CloudTenant name stays BeyondTechWorld. The site title is `system_name`.
- The biller is "Beyond Enterprise". That is `legal_name`. The biller row was not turned into a tenant.
- `general_settings.currency` is currencies id 3, code 003, name RWF. CloudTenant currency stays XAF. The settings row was not changed.

Live `cloud_tenants` is still 0 rows.

## 5. Internal entitlement strategy

Platform policy, not a fake subscription. Access for WhatsApp Hub, Sales & Invoices, and Rentals is the entitlement table. Existing ERP routes do not consult subscriptions, so current Beyond users are not sent to a trial-expired page.

## 6. Membership strategy

`cloud:create-internal-tenant` writes memberships. It does not change `users.role_id`.

| ERP role_id | Name | Cloud membership |
| --- | --- | --- |
| 1 | Admin | none. Platform super admin. |
| 2 | Owner, active | OWNER of BeyondTechWorld. Extra active owners would be ADMIN. |
| 2 | Owner, inactive | none |
| 5 | Customer | none |
| 7, 9, 10, 11 | Fixed Assets, Editor, Approval, Signing | none (all inactive) |
| 12 | Vendor | none |
| 14 | Intern | none |
| 15 | Internship Supervisor, active | STAFF |

Rehearsal result: 1 owner, 0 tenant admins, 4 staff, 1 active platform admin left without a membership. Running the command twice kept a single company and the same membership counts.

## 7. Existing user classification

From production, active / total:

| Role | Users | Active | Class |
| --- | --- | --- | --- |
| Admin (1) | 4 | 1 | Platform super admin |
| Owner (2) | 7 | 1 | BeyondTechWorld owner |
| Customer (5) | 184 | 4 | Customer. Not a tenant admin. |
| Vendor (12) | 11 | 0 | Legacy vendor signup |
| Intern (14) | 10 | 10 | Learner. ERP role kept. No cloud membership. |
| Internship Supervisor (15) | 4 | 4 | BeyondTechWorld staff |
| Other inactive staff roles | 3 | 0 | Legacy |

`be_users` (shareholder portal) was not given Cloud membership.

## 8. Ownership map

See `BEYOND_CLOUD_OWNERSHIP_MAP.md`.

## 9. Proposed ownership columns

Prepared, not applied to production:

| Migration | Tables | Nullable | Global scope |
| --- | --- | --- | --- |
| `2026_10_02_140000_create_cloud_internal_entitlements` | cloud_internal_entitlements | n/a | no |
| `2026_10_02_141000_add_nullable_cloud_tenant_to_catalog` | products, categories, brands, units, warehouses, customers, suppliers | yes | no |
| `2026_10_02_142000_add_nullable_cloud_tenant_to_sales` | sales, quotations, payments | yes | no |
| `2026_10_02_143000_add_nullable_cloud_tenant_to_rentals` | bookings | yes | no |
| `2026_10_02_144000_add_nullable_cloud_tenant_to_whatsapp` | whatsapp_contacts, whatsapp_conversations | yes | no |

Foreign key to `cloud_tenants`, `ON DELETE RESTRICT`, so deleting a company cannot delete business rows. Line tables do not get a duplicate column.

Deploy does not migrate unless a path is passed. These files must not be passed to production migrate until approved.

## 10. Direct-id security inventory

Not fixed in this phase. These lookups load by primary key with no CloudTenant check. Today that is safe only because there is one company.

| Controller | Examples |
| --- | --- |
| ProductController | `Product::findOrFail` / `find` at lines 761, 953, 1170, 1420 |
| SaleController | `Sale::find` at lines 71, 1280, 2273, 2303, 2334, 2620, 2638, 3238, 3349 |
| SaleInvoiceVerifyController | `Sale::find` |
| QuotationController | `Quotation::findOrFail` / `find` at lines 567, 1176, 1240, 1408, 1510, 1623, 1657, 1672, 1699, 1711 |
| CustomerController | `Customer::findOrFail` / `find` |
| BookingController | `Booking::find` / `findOrFail` |
| WhatsAppHubController | conversation, call, group, document request, verification session by id |
| WhatsAppAssistantController | knowledge row and conversation by id |
| WhatsAppRentalController | rental request by id |
| WhatsAppLeadController | `Lead::findOrFail` |
| AssistantToolExecutor | `Product::find`, `Quotation::find`, `Booking::find` inside the recognized customer id |

Phase 1D should require the row's company (or its parent company) to match `CloudTenantContext` after context is actually set on those requests.

## 11. Migration rehearsal command

`php artisan cloud:rehearse-beyond-migration`

Default is dry-run. `--execute` writes NULL `cloud_tenant_id` values. It refuses when the database name is `beyondtechworld_laravel`. That refusal was executed against the live name and exited 1 with no write. Live products stayed 471 and live tenants stayed 0.

`--seed-test-tenant` inserts the test company and is also refused on the live database name.

## 12. Dry-run results

On the restored copy, before any write:

| Table | Total | Unowned | Would assign | Ambiguous |
| --- | --- | --- | --- | --- |
| products | 471 | 471 | 471 | 0 |
| categories | 32 | 32 | 32 | 0 |
| brands | 35 | 35 | 35 | 0 |
| units | 18 | 18 | 18 | 0 |
| warehouses | 2 | 2 | 2 | 0 |
| customers | 744 | 744 | 744 | 0 |
| suppliers | 1 | 1 | 1 | 0 |
| sales | 30 | 30 | 30 | 0 |
| quotations | 25 | 25 | 25 | 0 |
| payments | 30 | 30 | 30 | 0 |
| bookings | 64 | 64 | 64 | 0 |
| whatsapp_contacts | 1494 | 1494 | 1494 | 0 |
| whatsapp_conversations | 172 | 172 | 172 | 0 |

Derived tables were skipped: product_sales 66, product_quotation 63, booking_products 61, whatsapp_messages 2312.

## 13. Rehearsal database results

Sequence used:

1. Backup of live `beyondtechworld_laravel`.
2. Restore into `beyond_cloud_rehearsal` (not the live database).
3. Run the five Phase 1C migrations on that copy only.
4. `cloud:create-internal-tenant` twice.
5. Dry-run, then `--execute`.
6. `--seed-test-tenant`.
7. Second execute. Ambiguous rows were not moved.
8. MAI product search on the copy.
9. `migrate:rollback --step=5`.
10. Restore the backup again.
11. Drop `beyond_cloud_rehearsal` and delete the temporary code copy.

Live schema was checked after the rehearsal migrations and after the refused execute. It did not gain `products.cloud_tenant_id`.

## 14. Row count verification

Before count equalled after count on every table in section 12. Details: `BEYOND_CLOUD_1C_DATA_INTEGRITY.md`.

## 15. Ambiguous data

No production row was ambiguous. The only ambiguous rows were the two test records created after the Beyond write. They stayed on the test company.

## 16. WhatsApp migration plan

Do not change production in this order until each step is true:

1. Nullable `cloud_tenant_id` on `whatsapp_contacts` and `whatsapp_conversations` (prepared, not live).
2. Backfill Beyond rows on a rehearsal copy, then on production only after approval.
3. Add `whatsapp_connections` owned by a CloudTenant (provider account id, not a phone).
4. Point conversations at a connection.
5. Teach the webhook to resolve provider connection → CloudTenant.
6. Only then replace the global unique key.

Future shape:

```
CloudTenant
  → WhatsAppConnection
    → Conversations
      → Messages
    → Contacts
```

A webhook must not resolve CloudTenant from the sender phone. Multiple connections are not activated.

## 17. normalized_phone migration plan

Today: `whatsapp_contacts_normalized_phone_unique` on `normalized_phone` alone. Rehearsal confirmed this index is still there after the nullable column.

Later unique key:

```
UNIQUE (cloud_tenant_id, normalized_phone)
```

Safe order:

1. Column exists and is NOT NULL for every contact that must stay unique.
2. Beyond contacts are backfilled.
3. Queries and webhooks are tenant-aware.
4. Drop the single-column unique key and add the pair.
5. Do this on a rehearsal copy first.

Doing step 4 early would allow duplicate phones before the app can tell companies apart, or it would reject a second company. It was not done.

## 18. MAI tenantization inventory

Tenant identity must come from `CloudTenantContext` on the server. It must not be taken from the model prompt.

`AssistantToolExecutor::toolSearchRentalProducts` uses `Product::query()` with no company filter (line 126). `Product::find` is used for one catalogue id (line 496). Quotation and booking tools filter by `customer_id` and then `find($id)`.

Tools that read ERP data and will need server context later:

| Tool area | Records |
| --- | --- |
| search_rental_products, get_rental_product_information, search_event_products, build_event_solution | Product, event packages |
| create_rental_quotation, create_event_quotation_draft, get_customer_quotations, get_customer_quotation_details, get_quotation_status | Quotation |
| get_customer_bookings, get_booking_status, request_rental_booking | Booking / rental |
| get_internship_summary, get_current_internship_task, submit_internship_work | Internship |
| get_attendance_status, check_in, check_out | Attendance |
| list_available_documents, find_my_documents, request_document | Documents |
| get_my_tenancy, get_rent_balance, create_maintenance_request | Property tenancy |
| get_current_lead, get_open_leads_summary, get_pending_quotation_summary | Lead, quotation |

`get_my_tenancy` is property occupancy, not CloudTenant.

Global scopes stay off so this query still returns Beyond products when no context is set.

## 19. Settings classification

See `BEYOND_CLOUD_SETTINGS_CLASSIFICATION.md`. `general_settings` was not split.

## 20. File migration plan

No production file was moved.

| Files | Current place | Later place |
| --- | --- | --- |
| Company logo | `public/logo/` and `public/branding/` | `storage/app/cloud-tenants/{uuid}/branding/` |
| Portal hero | `storage/app/tenants/{uuid}/hero.*` | same uuid folder, renamed under `cloud-tenants/{uuid}/` when portal files move |
| Product images | `public/images/product/` | `storage/app/cloud-tenants/{uuid}/products/` |
| Sale / quotation documents | path on the sale or quotation row | `.../{uuid}/documents/` |
| Rental contracts | booking / contract attachment tables | `.../{uuid}/contracts/` |
| WhatsApp media | webhook / message storage | `.../{uuid}/whatsapp/` |
| Letters and internship files | current document disks | `.../{uuid}/documents/` |

Move only after the row's `cloud_tenant_id` is known. Keep the old path working until each reader is updated.

## 21. Queue / context plan

`CloudTenantContext` is a request singleton. It is set today by portal membership middleware and cleared with `clear()`.

`CloudTenantContextRunner::run($cloudTenantId, callback)` loads that company, runs the job, and clears the context in `finally`. A worker must pass the id on the job payload. It must not trust a static value left by the previous job.

Use this later for WhatsApp jobs, PDF jobs, notifications, and the scheduler. Those jobs were not rewritten in Phase 1C. The runner is covered by `CloudInternalTenantTest`.

## 22. Regression results

Rehearsal, not the live site:

- Login was not clicked. User rows and `role_id` values were unchanged.
- Product search through the MAI tool returned 8 active Beyond speakers. The test product was not in the list.
- Follow-up price read used the same tool. Examples: DOUBLE BASS SPEAKER BEYOND day rate 20000, MACKIE LOW SPEAKER 10000, NEXO MID 15IN SPEAKER 12000, SPEAKER BEHRINGER SINGLE LOW ACTIVE 15000. Some speaker rows have a day rate of 0 in `rent_price_per_day`; that is existing data, not an empty catalogue.
- Sales, quotations, bookings, payments, and WhatsApp messages still joined to their parents (0 orphans).
- Internship, attendance, and documents were not given a tenant scope, so their current queries are unchanged.
- `App\Property\Tenancy` remains table `tenancies`. `App\Cloud\CloudTenant` remains table `cloud_tenants`. The test asserts those class names and table names differ.

A full browser pass of every ERP screen was not run against production, because this phase must not change live behavior. The cloud test suites passed.

## 23. Rollback results

On the rehearsal database, `migrate:rollback --step=5` dropped the new columns and the entitlement table. The product count stayed intact (472 while the test row still existed).

Reloading `database.sql.gz` returned products to 471, sales to 30, customers to 744, `cloud_tenants` to 0, and removed `products.cloud_tenant_id`.

Recovery procedure:

1. Stop writes to the target database.
2. Load `/var/backups/beyondtechworld/20261002-114023-phase1c/database.sql.gz` into a replacement database, or into the target only if that restore is approved.
3. Confirm product count 471 and no `cloud_tenant_id` on `products`.
4. Point the application back only after that check.

If the only change was the Phase 1C migrations, `php artisan migrate:rollback --step=5` drops the columns and keeps the business rows. The gzip restore is the full recovery.

## 24. Data-integrity report

`BEYOND_CLOUD_1C_DATA_INTEGRITY.md`.

## 25. Production readiness

**PRODUCTION MIGRATION READY: NO**

Reasons:

- Rehearsal passed, and that is not approval to write the live database.
- Nullable columns are not on production. Adding them is a schema change.
- Pages and MAI still load by id or `Product::query()` with no company check. Scopes must stay off until those paths set `CloudTenantContext`.
- WhatsApp `normalized_phone` is still globally unique. That is correct until connections and webhooks resolve a company.
- The temporary rehearsal database and code copy were removed. Live tenants are still 0.

`cloud:rehearse-beyond-migration --execute` will keep refusing `beyondtechworld_laravel`.

## 26. Recommendation for Phase 1D

Do not start Phase 1D until this report is accepted.

Phase 1D should:

1. Leave global scopes off.
2. Set `CloudTenantContext` on ERP requests and on the jobs that need it, using the runner's `finally` clear.
3. Then add company checks to the direct-id list in section 10, one module at a time.
4. Add the WhatsApp connection table and webhook resolution before any unique-key change.
5. Keep public company creation closed until a second company can be isolated without hiding Beyond data.

## Stop

Phase 1C stops here. Not done, on purpose:

- global tenant scopes
- public company onboarding
- a second real company
- live WhatsApp tenantizing
- changing `normalized_phone` uniqueness
- customer subscription enforcement against Beyond
- live ownership backfill
