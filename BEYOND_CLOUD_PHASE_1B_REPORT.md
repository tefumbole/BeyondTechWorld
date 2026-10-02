# Beyond Cloud — Phase 1B report

Status: **foundation only**. Phase 1C has not been started.

The SaaS company is `App\Cloud\CloudTenant` on `cloud_tenants`. There is no `App\Tenant` class and no `tenants` table. Property renters stay `App\Property\Tenancy` / `tenancies`.

This phase was not applied to production. The live BeyondTechWorld database is unchanged. No Beyond company row was inserted. No business table received `cloud_tenant_id`.

## 1. Migration created

`laravel-app/database/migrations/2026_10_01_160000_create_cloud_platform_foundation.php`

`up()` creates the eight new tables and then installs the global module, plan, and payment-method catalog. `down()` drops only those tables, in dependency order. It does not alter an existing column.

## 2. Tables created

| Table | Kind |
|---|---|
| `cloud_modules` | Global platform catalog |
| `cloud_plans` | Global platform prices |
| `cloud_tenants` | One company workspace |
| `cloud_tenant_memberships` | Users in a company |
| `cloud_subscriptions` | A company's subscription to a plan |
| `cloud_tenant_settings` | Extra company configuration |
| `cloud_payment_methods` | Global MoMo and VISA methods |
| `cloud_trial_claims` | One trial per phone number per module |

`cloud_tenants` columns: `id`, `uuid`, `name`, `legal_name`, `slug`, `system_name`, `type`, `status`, `email`, `phone`, `country`, `city`, `address`, `currency` (default `XAF`), `timezone` (default `Africa/Douala`), `logo_path`, `created_by`, timestamps.

No `deleted_at`. The rest of the ERP does not soft-delete users or sales. A company ends by status (`SUSPENDED` / `CANCELLED`). A person leaves a company by membership status `REMOVED`.

## 3. Models created

- `App\Cloud\CloudTenant`
- `App\Cloud\CloudTenantMembership`
- `App\Cloud\CloudModule`
- `App\Cloud\CloudPlan`
- `App\Cloud\CloudSubscription`
- `App\Cloud\CloudTenantSetting`
- `App\Cloud\CloudPaymentMethod`
- `App\Cloud\CloudTrialClaim`

No route, controller, or Blade screen was added. Existing invoices and pages do not read these models.

## 4. Relationships

- `CloudTenant` has many memberships, subscriptions, and settings, and belongs to `User` as `creator`.
- `CloudTenantMembership` belongs to `cloudTenant` and `user`.
- `CloudModule` has many plans.
- `CloudPlan` belongs to `module` and has many subscriptions.
- `CloudSubscription` belongs to `cloudTenant` and `plan`.
- `CloudTenantSetting` belongs to `cloudTenant`.

Product, Sale, Customer, Quotation, Booking, and WhatsApp models were not given a Cloud relationship.

## 5. Constants and statuses

PHP 7.4 class constants, not PHP 8 enums.

| Class | Values |
|---|---|
| `CloudTenantType` | `INTERNAL`, `CUSTOMER` |
| `CloudTenantStatus` | `PENDING`, `ACTIVE`, `SUSPENDED`, `CANCELLED` |
| `CloudMembershipRole` | `OWNER`, `ADMIN`, `STAFF` |
| `CloudMembershipStatus` | `INVITED`, `ACTIVE`, `SUSPENDED`, `REMOVED` |
| `CloudSubscriptionStatus` | `TRIALING`, `ACTIVE`, `PAST_DUE`, `SUSPENDED`, `CANCELLED`, `EXPIRED` |
| `CloudModuleCode` | `WHATSAPP_HUB`, `SALES_INVOICES`, `RENTALS` |
| `CloudBillingInterval` | `MONTH` |
| `CloudTrialUnit` | `HOUR`, `MONTH` |
| `CloudPaymentMethodCode` | `MOMO`, `VISA` |

Company status and subscription status are different fields on different tables. A company can be `ACTIVE` while a module subscription is `EXPIRED`.

## 6. Seeded modules

Installed by `App\Services\Cloud\CloudCatalog::install()` at the end of the migration. `database/seeds/CloudPlatformSeeder.php` calls the same method and is not wired into `DatabaseSeeder`.

| Code | Name | Description |
|---|---|---|
| `WHATSAPP_HUB` | WhatsApp Hub | Team WhatsApp inbox, conversations, MAI and supported automation. |
| `SALES_INVOICES` | Sales & Invoices | Products, customers, sales, quotations, invoices and related business operations. |
| `RENTALS` | Rentals | Rental inventory, bookings, events and rental operations. |

## 7. Seeded plans and prices

Prices are rows in `cloud_plans`. No controller, middleware, Blade view, JavaScript, or MAI prompt contains these amounts.

| Plan code | Module | Price | Currency | Interval | Trial |
|---|---|---|---|---|---|
| `WHATSAPP_HUB_MONTHLY` | `WHATSAPP_HUB` | 10000.00 | XAF | MONTH | 24 hours |
| `SALES_INVOICES_MONTHLY` | `SALES_INVOICES` | 5000.00 | XAF | MONTH | 24 hours |
| `RENTALS_MONTHLY` | `RENTALS` | 5000.00 | XAF | MONTH | 24 hours |

Paid checkout for these plans uses the methods already in the system:

| Code | Name | Existing provider |
|---|---|---|
| `MOMO` | MoMo | Campay, the same mobile-money link used elsewhere (`payment_options` MOMO) |
| `VISA` | VISA | Stripe card checkout, the same card path used elsewhere |

Secrets stay in the existing Campay and Stripe configuration. This phase does not open a checkout and does not charge anyone.

`CloudCatalog::install()` uses `firstOrCreate`. Running it again does not overwrite a price that was edited later. `migrate:rollback` on this migration drops the plan rows along with the new tables.

## 8. Trial configuration

Each seeded plan has `trial_value = 24` and `trial_unit = HOUR`. No `cloud_subscriptions` row is created for BeyondTechWorld. No trial clock is started until a later phase calls `CloudTrialEligibility`.

`cloud_trial_claims` is unique on `(normalized_phone, cloud_module_id)`. `CloudTrialEligibility::claim()` records the phone with the same normalizer used for WhatsApp numbers, so `+237 677…` and `237677…` are the same number. A second claim for that module throws. The same phone may still take a different module's trial, because that is a different trial. Deleting the company does not delete the claim (`cloud_tenant_id` is set null), so removing the workspace does not grant the trial again.

## 9. Tenant settings

`cloud_tenant_settings` is unique on `(cloud_tenant_id, key)`. The same key can exist for two companies. `value` is long text. `type` is optional.

This table is for future branding, landing copy, and MAI identity. It is not read by the current letterhead, invoice, or site logo code. Do not put WhatsApp, OpenAI, or payment secrets in it.

`system_name` and `logo_path` also exist as columns on `cloud_tenants` because they will be looked up often. They are unused by the live ERP.

## 10. Membership

`cloud_tenant_memberships` is unique on `(cloud_tenant_id, user_id)`. One `users` row can belong to several companies. There is no new user table.

`is_owner` marks the primary owner. `membership_role` can also be `ADMIN` or `STAFF`, so more than one administrator can exist later. `role_id` is a nullable integer with an index and **no foreign key**. It is reserved so a later phase can point at the existing Spatie/`roles` row without rewriting RBAC now.

`CloudTenantMembership::remove()` sets status `REMOVED` and clears `is_owner`. The `users` row stays. Deleting the membership row also leaves the user in place. Deleting the user removes that user's memberships (`onDelete cascade`). Deleting a company removes its memberships, settings, and subscriptions, not the user accounts (`created_by` is `set null`).

Platform super admin is not a membership role. Today's ERP still treats `users.role_id <= 2`, and Spatie names such as `Admin` and `Super Admin`, as install-wide administrators. A later phase should add an explicit platform flag for those people. A Cloud `OWNER` must not be given that flag automatically.

## 11. CloudTenantContext

`App\Services\Cloud\CloudTenantContext` can `set`, `get`, `id`, `clear`, and `requireTenant`. It is registered as a singleton in `AppServiceProvider`. No middleware, job, WhatsApp webhook, or ERP controller calls it. If nothing has called `set`, `requireTenant()` throws. An empty context does not mean "every company".

## 12. Tests added

`laravel-app/tests/Feature/CloudPlatformFoundationTest.php`

`laravel-app/database/factories/CloudFactory.php` defines factories for the Cloud models. The feature tests create rows directly so they do not depend on the full `users` schema.

## 13. Test results

```
OK (7 tests, 84 assertions)
```

Command: `php -d memory_limit=512M vendor/bin/phpunit tests/Feature/CloudPlatformFoundationTest.php`

Covered: catalog prices, a 24-hour trial from the database, MoMo and VISA rows, one trial per phone number per module, unique uuid and slug, one user in two companies, membership removal without deleting the user, the same setting key on two companies, duplicate key on one company rejected, `TRIALING` and `ACTIVE` subscriptions, context set/clear/require, no `App\Tenant` import under `app/Cloud`, and no Cloud global scope on Product, Sale, Customer, Quotation, Booking, or WhatsAppContact.

## 14. Regression results

On the in-memory test database, a pre-existing `products` row and a pre-existing `whatsapp_contacts` row were unchanged after the migration. `whatsapp_contacts.normalized_phone` stayed unique. Rollback dropped only the Cloud tables and left those rows in place.

`tests/Feature/WhatsAppPhase3Test.php` with filter `test_disabled_assistant_does_not_reply` still passes, so the unused context binding does not stop the existing WhatsApp test boot.

Login, dashboard, and the live hub were not clicked. This phase adds no route and no query on the existing screens. Production was not migrated, so those screens are still on the previous schema.

## 15. Schema before and after

Production schema before and after this phase is the same, because the migration was not run there.

The migration file contains `Schema::create` for the eight `cloud_*` tables only. It does not call `Schema::table` on products, sales, bookings, quotations, customers, or WhatsApp tables.

## 16. Existing data

No production `UPDATE` was run. The catalog seed runs only when this migration runs, and it inserts module and plan rows. It does not insert a CloudTenant. That seed has not been executed on production.

## 17. No cloud_tenant_id on operational tables

Confirmed by the migration source and by the test: the stand-in `products` and `whatsapp_contacts` tables had no `cloud_tenant_id` after migrate.

## 18. Known limitations

- Public `/c/{slug}` routes, onboarding, and the Subscriptions admin screen are not built.
- `CloudTenantContext` is empty during normal ERP requests.
- Nothing licenses a module yet. `RequireSubscribedModule` does not exist.
- WhatsApp, OpenAI, Campay, and PawaPay credentials stay installation-wide.
- `whatsapp_contacts.normalized_phone` is still globally unique. Two companies cannot both store the same number until a later migration replaces that with a per-company unique key.
- MAI still runs `Product::query()` with no company owner.
- Sales, quotations, and bookings are still opened by numeric id with no company check.
- `role_id` on a membership is not enforced against Spatie roles.
- Rollback of this migration deletes plan rows, including any prices edited after the seed. Do that only while these tables are still empty of real companies.

## 19. Issues discovered

No Phase 1B defect turned up in the new tests. The audit findings that must wait are unchanged: global WhatsApp phone uniqueness, unscoped product search, and direct document ids. They were not patched, on purpose.

## 20. Recommendation for Phase 1C

Do not start Phase 1C until this report is accepted.

When it starts, keep it to one company row:

1. Restore the `20261001-134320-4e7d013b` backup onto a copy. Do not use production as the rehearsal.
2. Run this Phase 1B migration on that copy.
3. Insert one `cloud_tenants` row: name BeyondTechWorld, slug `beyondtechworld`, type `INTERNAL`, status `ACTIVE`, currency `XAF`, timezone `Africa/Douala`.
4. Add `cloud_tenant_memberships` only for the real platform administrators, with `membership_role = OWNER` or `ADMIN`. Do not treat every `role_id <= 2` user as a platform super admin.
5. Still do not add `cloud_tenant_id` to products, sales, bookings, or WhatsApp tables in that same step. Write the backfill counts and the rollback on the copy first. Production backfill stays behind that rehearsal.
6. Leave `App\Property\Tenancy` and the `tenancies` table alone.

## What was not done

No backfill. No product, sale, invoice, quotation, rental, WhatsApp, or MAI change for tenancy. No public "Build Your Own Company" page. Production migrate was not run.

Admin Subscriptions (`/admin/subscriptions`, role 1 or 2) can change plan price, trial length, and active flag. The quoted price on an open trial is not rewritten. Company portal is `/cloud/register` and `/cloud/login` (not the ERP guest redirect). Owners can start one trial per phone per module, upload a hero image, and save business rules in settings. MoMo and VISA checkout creates a pending payment and does not mark paid from the browser return alone.
