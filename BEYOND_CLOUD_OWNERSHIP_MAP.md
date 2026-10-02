# Beyond Cloud ownership map

Counts are exact `COUNT(*)` from the 2026-10-02 rehearsal copy of production, unless marked `~` (information_schema estimate from the same backup). No customer names are listed.

`cloud_tenant_id` is not on any of these tables in live production. The nullable column exists only in rehearsal migrations that were rolled back.

Global Eloquent tenant scopes stay off.

## Classes

| Class | Meaning |
| --- | --- |
| GLOBAL | One row set for the whole installation. No `cloud_tenant_id`. |
| CLOUD-TENANT OWNED | A company owns the row. |
| PROPERTY-SPECIFIC | Property occupancy. Not a CloudTenant. |
| PLATFORM-SPECIFIC | Beyond Cloud platform tables. |
| SHARED / NEEDS DECISION | Do not assign in this phase. |

## Direct ownership prepared in Phase 1C

Parent is the CloudTenant. Backfill: set `cloud_tenant_id` only where it is NULL and the database is the BeyondTechWorld installation. A non-null id for a different company is AMBIGUOUS and is not overwritten.

Nullable transition: yes. Foreign key: `cloud_tenants.id`, `ON DELETE RESTRICT`. Index: yes. Unique constraints: unchanged in this phase.

| Table | Rows | Group | Child rows that derive ownership | Unique constraints touched |
| --- | --- | --- | --- | --- |
| products | 471 | A catalog | product_warehouse, product_variants, product_sales, product_quotation, booking_products | none |
| categories | 32 | A | products.category_id | none |
| brands | 35 | A | products.brand_id | none |
| units | 18 | A | products.unit_id | none |
| warehouses | 2 | A | product_warehouse, sales.warehouse_id | none |
| customers | 744 | A | sales, quotations, bookings | none |
| suppliers | 1 | A | purchases | none |
| sales | 30 | B | product_sales, payments | none |
| quotations | 25 | B | product_quotation | none |
| payments | 30 | B | payment_with_* | none |
| bookings | 64 | C rentals | booking_products, booking_contracts | none |
| whatsapp_contacts | 1494 | D | whatsapp_contact_links | do not change `normalized_phone` unique yet |
| whatsapp_conversations | 172 | D | whatsapp_messages | none |

There is no `invoices` table. A sale is the invoice document (`sales.document`, invoice format in settings).

## Derived ownership (no second column)

| Table | Rows | Parent | Backfill |
| --- | --- | --- | --- |
| product_sales | 66 | sales | skip; parent sale carries the company |
| product_quotation | 63 | quotations | skip |
| booking_products | 61 | bookings | skip |
| whatsapp_messages | 2312 | whatsapp_conversations | skip |

Rehearsal found 0 orphan rows for sale→customer, quotation→customer, booking→customer, line→header, and message→conversation.

## CLOUD-TENANT OWNED, column not added yet

These belong to a company later. Phase 1C did not add `cloud_tenant_id` to them. Assign them only after the parent header is assigned, or add a column in a later group if a query cannot join safely.

| Area | Tables | Ownership source | Approx rows |
| --- | --- | --- | --- |
| Purchases | purchases, product_purchases, return_purchases, purchase_product_return | supplier / warehouse header | 3 purchases |
| Returns | returns, product_returns | sale | 0 |
| Stock | adjustments, product_adjustments, transfers, product_transfer, stock_counts, product_batches, product_warehouse, product_variants, variants | warehouse or product | warehouse stock ~206, variants 38 |
| People extras | customer_groups, deliveries, expenses, expense_categories, deposits, accounts, cash_registers | company | customers groups 3, expenses 1, deposits 1, accounts ~2 |
| Events | btw_events, event_packages, event_assignments, event_publications, event_worker_profiles | company | events 3, packages 13 |
| Leads | leads, lead_activities | company | leads 35 |
| Employees | employees, attendances, departments, payrolls, hr_* | company | employees 4, attendances 8 |
| Internship | internship_programs, internship_enrolments, internship_submissions, internship_program_tasks | company | enrolments 12, tasks ~1539 |
| Tasks | tasks, task_assignments, task_updates | company | tasks 5 |
| Documents | letters, letter_templates, contract_templates, contract_documents | company | letters 17, contract templates 12 |
| Orders | orders, order_products | company storefront | orders ~220 |
| Announcements | wa_announcements, announcements, message_delivery_batches | company | wa_announcements 8 |
| WhatsApp extras | whatsapp_calls, whatsapp_webhook_events, whatsapp_rental_requests, whatsapp_settings, whatsapp_groups | connection, then company | webhook events ~28970 |
| MAI | assistant_knowledge, assistant_memories, assistant_activities | company | knowledge 11, memories 30 |
| Public content | courses, gallery_items, reviews, online_invitations, funeral_* | company site | courses 7, gallery 9 |
| Jobs | jobs, job_postings, applications | company | jobs 2, applications ~9 |

`whatsapp_webhook_events` should follow the WhatsApp connection, not the sender phone.

## GLOBAL

| Table | Why |
| --- | --- |
| migrations | schema history |
| jobs, failed_jobs | queue infrastructure |
| password_resets | auth infrastructure |
| permissions, roles, role_has_permissions | platform RBAC. Company membership does not replace this. |
| languages | installation |
| currencies | installation list. Company choice is a column, not a copy of the table. |
| general_settings | one installation row until the classification in `BEYOND_CLOUD_SETTINGS_CLASSIFICATION.md` is migrated |
| users | a person is global. Membership joins them to a company. |

## PLATFORM-SPECIFIC

`cloud_tenants`, `cloud_tenant_memberships`, `cloud_modules`, `cloud_plans`, `cloud_subscriptions`, `cloud_subscription_payments`, `cloud_payment_methods`, `cloud_trial_claims`, `cloud_tenant_settings`, `cloud_internal_entitlements`.

These already carry `cloud_tenant_id` where a row belongs to one company. Modules, plans, and payment methods are platform catalogs.

## PROPERTY-SPECIFIC

`properties`, `property_units`, `tenancies`, `property_rent_payments`, `property_maintenance_requests`, `property_maintenance_attachments`, `property_utility_accounts`, `property_activities`, `rent_obligations`, `rent_reminder_logs`.

`App\Property\Tenancy` / `tenancies` is occupancy. It is not `App\Cloud\CloudTenant`. Phase 1C did not rename it and did not add `cloud_tenant_id` to it. A later phase can attach a property to a CloudTenant without merging the two nouns.

## SHARED / NEEDS DECISION

| Table | Why it is not assigned yet |
| --- | --- |
| billers | Invoice header. Future child of a CloudTenant. Not the tenant itself. |
| be_users, be_profiles, be_otp_sessions, be_timesheet_* | Shareholder / timesheet portal (`beyond` guard), separate from `users`. |
| shareholders | Equity register. |
| activity_logs | May mix platform and company actions. |
| notifications | Recipient is a user; the subject may be company data. |
| shared_message_serials | Global serial helper. |
| taxes | Could be platform rates or company rates. |
| holidays | Company calendar vs platform calendar. |
| leaders | Public site people. |
| site_settings | Public site. Company specific later, not bulk-copied now. |

## Ambiguous rule

A row is AMBIGUOUS when `cloud_tenant_id` is already set to a company other than BeyondTechWorld. The rehearsal command does not change that row. Child rows are not guessed when their parent is missing; the relationship check reports orphans instead.
