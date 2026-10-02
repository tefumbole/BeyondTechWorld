# Beyond Cloud — Phase 1F production report

Status: **PHASE 1F CODE DEPLOYMENT PASSED**

Public company signup was left **CLOSED**. No customer tenant was created. SMS was not activated. Payment providers were not activated.

## Backup

| Item | Value |
| --- | --- |
| UTC timestamp | 2026-10-02 20:16:19 UTC |
| Database | `beyondtechworld_laravel` |
| Path | `/var/backups/beyondtechworld/20261002-201619-phase1f-prod/database.sql.gz` |
| SHA-256 | `dbf2faf31bfb0fd830c60a4428aa75499dcbff7759507d8b1c2fea19165a458d` |
| Commit at backup | `f8c9e0be95ff635512e8650617a3a34732d29cf5` |
| gzip check | passed |

The dump was restored into `beyond_cloud_1f_restorecheck` and that database was dropped. Restored counts: products 471, customers 744, sales 30, quotations 25, payments 30, bookings 64, subscriptions 0, tenants 1, plans 4. Tenant INTERNAL ACTIVE `beyondtechworld`. Plans: WhatsApp Hub 10,000 XAF, Sales & Invoices 5,000 XAF, Rentals 5,000 XAF, Messaging 5,000 XAF. Each trial is 24 HOUR. Internal entitlements: WHATSAPP_HUB, SALES_INVOICES, RENTALS.

## Commits

| Item | Value |
| --- | --- |
| Production before | `f8c9e0be95ff635512e8650617a3a34732d29cf5` |
| Production after | `be426d6cb45460f80afd13b4df0738abbb481715` |
| Included history | `885d69e` records the Phase 1E production report only. `be426d6` is the onboarding code. |
| Working tree | Server runtime files (cache, birthday assets, one product image, backup env files) were already dirty and were not reset. |
| Migrations run | none |

Phase 1F adds no migration. Messaging Hub tables were not deployed. The last applied migration remains `2026_10_02_200000_cloud_subscription_enforcement`.

The onboarding commit does not include the SMS provider, ledger, Infobip adapter, SMS migrations, or SMS send routes. Those files remain in the local working tree and were not pushed.

## Deployment

`git pull` fast-forwarded `f8c9e0be` to `be426d6c`. The deploy script skipped migrations, cleared view, route, config, and application caches, and reloaded PHP 7.4-FPM. The existing letterhead warning appeared again and is not an onboarding failure.

Pre-deploy `php artisan cloud:audit-ownership`: unowned 0, invalid 0, cross-tenant 0.

## Tenant and plans

BeyondTechWorld stayed INTERNAL and ACTIVE. Customer tenants stayed 0. Subscriptions stayed 0 before and after two scheduler runs (`changed=0` both times).

| Plan | Price | Trial |
| --- | --- | --- |
| WhatsApp Hub | 10,000.00 XAF | 24 HOUR |
| Sales & Invoices | 5,000.00 XAF | 24 HOUR |
| Rentals | 5,000.00 XAF | 24 HOUR |
| Messaging | 5,000.00 XAF | 24 HOUR |

Prices were read from `cloud_plans`. They were not hardcoded during deploy. `public_onboarding` is false. `payments_live` is false. Grace is 0. Billing sandbox is off.

Platform entitlements on the internal tenant are WhatsApp Hub, Sales & Invoices, and Rentals. A direct check of the Messaging module code returns `not_entitled` because that row is not in `cloud_internal_entitlements`. Messaging writes are still allowed through the WhatsApp Hub entitlement (`messaging_write=yes`). That is the Phase 1E rule. The 5,000 XAF Messaging plan remains the customer plan and was not redefined around SMS.

## Registration switch

`GET /cloud/register` returned HTTP 200 and the text “Company signup is not open yet.”

`POST /cloud/register` returned HTTP 419. Tenant count stayed 1. Subscriptions stayed 0. No membership and no trial were created.

`/subscriptions` returned HTTP 200 and showed 5,000, 10,000, and 24 hour from the plan rows.

## Beyond, sales, and rentals

With the internal tenant context set: WhatsApp Hub, Sales & Invoices, and Rentals are `full` / `platform_entitlement`. Products in that context: 471. Without a tenant context: 0. Product id 99999999 was not found. No subscription wall was applied to this tenant.

`/` `/login` `/rentals` returned HTTP 200. `/bookings/index` returned 302 to login. `/bookings` remains 404, as before. No sale, quotation, payment, or booking was created.

## Public tenant page

`/c/beyondtechworld` returned HTTP 404. The public page looks up CUSTOMER companies only. It does not sign a visitor in and does not switch the active company. No customer slug exists to open.

## WhatsApp and queue

Connection id 1, provider wasender, status ACTIVE, tenant 1. Contacts 2331. Conversations 181. No customer message was sent.

No job was reserved. `queue:restart` was broadcast, the queue was still idle, and only `beyondtechworld-whatsapp-queue` was restarted. New pid 3197152, user www-data, cwd `/var/www/beyondtechworld/laravel-app`, command `php artisan queue:work database --queue=whatsapp,default --sleep=1 --tries=3 --timeout=90`. Failed jobs stayed 13. No SMS route exists, so the worker has no SMS job to run.

## MAI

A diagnostic question, “What is two plus two?”, was answered “Two plus two equals four.” The call did not go through WhatsApp.

A rental-catalogue search for “speaker” under BeyondTechWorld returned 8 products, including DOUBLE BASS SPEAKER BEYOND, MACKIE LOW SPEAKER, and Mid speaker (BEHRINGER B1520 PRO). The same search with `cloud_tenant_id` 999 still returned those 8 products. The browser argument was ignored.

## Subscription enforcement and payments

INTERNAL access still comes from platform entitlements. The scheduler did not create a subscription or a trial for BeyondTechWorld. Stripe and Campay subscription webhooks returned HTTP 501. The sandbox webhook returned HTTP 404. No Pay action was used to mark a subscription ACTIVE.

PAYMENT PROVIDER PRODUCTION ACTIVATION: **NOT VALIDATED**

## SMS

SMS PRODUCTION SENDING: **DISABLED**

`config/messaging.php` is not on the server. There is no SMS route. No Infobip credential is required to boot the application. The customer messaging page says “SMS: Not available” and does not ask for a provider account. WhatsApp on that page remains “Not connected / Admin setup required” for a company that has no connection. BeyondTechWorld’s existing WaSender connection was not attached to any new company.

INFOBIP LIVE VALIDATION: **DEFERRED**

## Security checks

No request in the deployed register or public-page flow accepts `cloud_tenant_id` as the authority for company data. The company switch control is limited to companies the signed-in member already belongs to. Platform Admin was not given an OWNER membership. Customer tenants remain 0, so no new cross-tenant admin action was taken.

## Ownership after

After deploy, scheduler, and worker restart:

- unowned_total = 0
- invalid_tenant_total = 0
- cross_tenant_links = 0
- products 471, tenants 1, subscriptions 0

## Logs

Laravel log window after the deploy: no `MissingCloudTenant`, no Infobip call. One `production.ERROR` at 21:17:58 local time reported `Undefined class constant App\Providers\RouteServiceProvider::HOME` while application routes were being inspected. Public pages checked in the same window returned their normal codes. The queue error log had no new match for that window. Failed jobs did not increase.

## Local tests before the commit

Run on the Phase 1F tree with the SMS send routes excluded from the commit. PHPUnit did not call Infobip.

| Suite | Result |
| --- | --- |
| CloudOnboardingTest | 7 tests, 111 assertions, pass |
| CloudPortalTest | 3 tests, 42 assertions, pass |
| CloudTenantIsolationTest | 9 tests, 38 assertions, pass |
| CloudSubscriptionEnforcementTest | 10 tests, 111 assertions, pass |
| CloudInternalTenantTest | 3 tests, 36 assertions, pass |
| CloudPlatformFoundationTest | 7 tests, 99 assertions, pass |
| WhatsAppWebhookTest | 17 tests, 69 assertions, pass |

The local Messaging Hub and Infobip stub suites were run again after the SMS files were kept out of the commit. They still pass and still do not call Infobip. They were not deployed.

## Known limitations

A live CUSTOMER company was not created. The open registration path, a 24-hour customer trial, and a customer landing page were not exercised on production. That needs a separate decision. Signup stays closed.

WhatsApp for a new company is still admin setup. It does not share BeyondTechWorld’s session.

Online subscription payment is still not validated. Due today for a future trial remains 0 XAF until that trial is actually started.

SMS remains optional and deferred.

## Explicit status

```
PHASE 1F CODE DEPLOYMENT:
PASSED

PUBLIC COMPANY SIGNUP:
CLOSED

PRODUCTION ONBOARDING VALIDATION:
REQUIRES CONTROLLED CUSTOMER TEST

PAYMENT PROVIDER PRODUCTION ACTIVATION:
NOT VALIDATED

WHATSAPP CUSTOMER SELF-CONNECTION:
ADMIN SETUP REQUIRED

SMS:
OPTIONAL / DEFERRED

SMS PRODUCTION SENDING:
DISABLED

INFOBIP LIVE VALIDATION:
DEFERRED

TENANT ISOLATION:
PASS

SUBSCRIPTION ENFORCEMENT:
PASS

WHATSAPP REGRESSION:
PASS

MAI REGRESSION:
PASS

OWNERSHIP AUDIT:
PASS
```
