# Beyond Cloud — Phase 1E production report

Status: **PHASE 1E PRODUCTION VALIDATION PASSED**

PAYMENT PROVIDER PRODUCTION ACTIVATION: **NOT VALIDATED**

Rollback required: **NO**

Public company signup stays closed. No customer tenant was created. Phase 1F was not started. Live Campay and Stripe subscription webhooks were not activated. Prices and the 24-hour trial were not changed. Ownership columns were not changed to NOT NULL. SMS was not added.

## Before deployment

| Item | Value |
| --- | --- |
| Current production commit | `50c81a9d03a6ec1184d65d9a5bc306a18d46e445` |
| Target Phase 1E commit | `f8c9e0be95ff635512e8650617a3a34732d29cf5` |
| Working tree | Phase 1E was local-only on top of `50c81a9`. It was committed as `f8c9e0b` and pushed. No unrelated application changes were included. |
| Migrations pending | `2026_10_02_200000_cloud_subscription_enforcement.php` only |

The server working tree had runtime and upload files (cache, birthday assets, one product image, backup env files). Those were not committed and were not part of the deploy.

Pre-deploy `php artisan cloud:audit-ownership`: unowned 0, invalid 0, cross-tenant 0.

## Backup

| Item | Value |
| --- | --- |
| UTC timestamp | 2026-10-02 19:13:04 UTC |
| Database | `beyondtechworld_laravel` |
| Path | `/var/backups/beyondtechworld/20261002-191304-phase1e-prod/database.sql.gz` |
| Size | 7.7 MB |
| SHA-256 | `f31ba37f93bab3ddfaee0f2184b421d8b0f8935b79f0021127e88a4faa056647` |
| Commit at backup | `50c81a9d03a6ec1184d65d9a5bc306a18d46e445` |
| Target commit | `f8c9e0be95ff635512e8650617a3a34732d29cf5` |
| Migration level at backup | through `2026_10_02_180000_add_cloud_whatsapp_connection_and_tenant_phone` |

Restore check loaded the gzip into `beyond_cloud_1e_restorecheck` and then dropped that database. Restored counts: products 471, sales 30, customers 744, plans 4, subscriptions 0. Tenant INTERNAL ACTIVE `beyondtechworld`. Messaging 5000.00 XAF, trial 24 HOUR. Entitlements: WHATSAPP_HUB, SALES_INVOICES, RENTALS.

## Deployed commit and migration

Production HEAD after deploy: `f8c9e0be95ff635512e8650617a3a34732d29cf5`.

The only migration run was `2026_10_02_200000_cloud_subscription_enforcement.php`. It adds:

- `cloud_subscriptions.cancel_at_period_end`
- `cloud_module_trials`
- `cloud_subscription_events`
- `cloud_billing_events` with unique `(provider, event_id)` named `cloud_billing_event_unique`
- `cloud_subscription_notices`

It does not change plan prices, trial length, or operational `cloud_tenant_id` nullability. Config, route, view, and application caches were cleared by the normal deploy script. PHP 7.4-FPM was reloaded. The existing letterhead warning appeared again and is not a subscription failure.

## Cloud plans

Unchanged after deploy:

| Plan | Price | Trial | Interval |
| --- | --- | --- | --- |
| WhatsApp Hub | 10,000.00 XAF | 24 HOUR | MONTH |
| Sales & Invoices | 5,000.00 XAF | 24 HOUR | MONTH |
| Rentals | 5,000.00 XAF | 24 HOUR | MONTH |
| Messaging | 5,000.00 XAF | 24 HOUR | MONTH |

`/subscriptions` returned HTTP 200 and showed 5,000, 10,000, and 24 hours. The Blade source uses `$plan->price`, `$plan->trial_value`, and `$plan->billing_interval`.

Grace is `0`. Billing sandbox is off. Public onboarding is off.

## BeyondTechWorld

| Check | Result |
| --- | --- |
| Type / status / slug | INTERNAL / ACTIVE / beyondtechworld |
| Customer tenants | 0 |
| Subscriptions | 0 before and after the scheduler |
| WhatsApp Hub, Sales & Invoices, Rentals | full access, reason `platform_entitlement` |
| Messaging capability | allowed through the WhatsApp Hub entitlement |
| Module gate on sales, quotations, payments, bookings, rentals, WhatsApp hub | HTTP 200 for this tenant, not a subscribe or renew wall |
| Products visible in that context | 471 |
| Trial or subscription rows created for it | 0 |

The scheduler `cloud:process-subscriptions` was run twice. First pass changed 0 rows. Second pass changed 0 rows. BeyondTechWorld stayed INTERNAL and ACTIVE. Notices 0, trial-history rows 0, billing events 0.

## Registration and customer creation

`GET /cloud/register` is HTTP 200 and shows “Company signup is not open yet.”

A direct call of the register action returned HTTP 302 and did not create a tenant. Tenant count stayed 1. A browser POST without a valid session returned HTTP 419 and also created nothing.

No Alpha, Beta, trial, or demo company was created.

## Payments

PAYMENT PROVIDER PRODUCTION ACTIVATION: **NOT VALIDATED**

| Endpoint | HTTP |
| --- | --- |
| `POST /cloud/billing/webhook/stripe` | 501 |
| `POST /cloud/billing/webhook/campay` | 501 |
| `POST /cloud/billing/webhook/sandbox` | 404 while sandbox is off |

Portal and billing controllers do not assign `ACTIVE`. Activation stays in `CloudSubscriptionService::confirmPayment`, which checks tenant, amount, currency, and provider event id. Checkout amount comes from `quoted_price` or the plan row, not from the browser. No production webhook secret was added. No fake success was sent.

## Queue

Before the stop, no job was reserved. `php artisan queue:restart` was broadcast, the queue was confirmed idle, and only then was `beyondtechworld-whatsapp-queue` stopped. After deploy it was started as pid 3187706, command `php artisan queue:work database --queue=whatsapp,default --sleep=1 --tries=3 --timeout=90`, cwd `/var/www/beyondtechworld/laravel-app`.

`ProcessAssistantTurn` and the announcement batch job call `CloudModuleAccessService` when the job runs. Outbound sends for a CUSTOMER company without messaging entitlement return `messaging_not_entitled` without throwing. Inbound storage is unchanged. This was confirmed in the deployed code. No external tenant was created to exercise it, and no customer was messaged.

Failed jobs since 20:10 server time: 0. Waiting jobs stayed unreserved.

Recommendation, not a queue redesign: stop the worker only after `jobs.reserved_at` is empty, and send `queue:restart` first so the current job can finish. The earlier max-attempts failure on `ResolveWhatsAppContactNamesJob` happened when a worker was stopped while a job was reserved.

## Routes observed

| Path | HTTP |
| --- | --- |
| `/` | 200 |
| `/login` | 200 |
| `/subscriptions` | 200 |
| `/rentals` | 200 |
| `/dashboard`, `/products`, `/sales`, `/quotations`, `/bookings/index`, `/admin/whatsapp` | 302 to login |

`/bookings` itself has no index route and returns 404. The booking list is `/bookings/index`. None of these responses is a subscription, trial, or payment wall.

No new sale, quotation, payment, or booking was created.

## MAI and WhatsApp

Under BeyondTechWorld, “speaker” search returned 8 products. A supplied `cloud_tenant_id` was ignored. Sample day rates: DOUBLE BASS SPEAKER BEYOND 20000, MACKIE LOW SPEAKER 10000, Mid speaker (BEHRINGER B1520 PRO) 10000. The rental tool remains in the Beyond prompt because the platform entitlement allows it.

A general OpenAI question (“two plus two is four”) was answered.

WhatsApp contacts 2331 and conversations 181, all owned. No customer message was sent.

## Phase 1D regression

| Check | Result |
| --- | --- |
| No tenant context, product count | 0 |
| Product id 99999999 | not found |
| Job runner after an exception | context clear |
| MAI tenant argument | ignored |
| New unowned rows after deploy and worker activity | 0 |

## Ownership after

`php artisan cloud:audit-ownership` after the scheduler, worker start, and a short live window:

- unowned_total = 0
- invalid_tenant_total = 0
- cross_tenant_links = 0
- products 471, customers 744, sales 30, quotations 25, payments 30, bookings 64
- WhatsApp contacts 2331, conversations 181

Contacts were 2329 at the Phase 1D deploy and are 2331 now. The new rows are owned. That is normal traffic, not a null-ownership defect.

## Logs

No `MissingCloudTenant`, cross-tenant, module-denial, or production ERROR lines were recorded in the deployment window. No scheduler exception. No new queue failure.

## Known limitations

- Live Campay and Stripe subscription webhooks stay off until a separate validation.
- Grace remains 0 hours. A finished paid period is designed to become `PAST_DUE` and read-only. That path was not exercised on production because there is no customer subscription.
- ERP menu items are not hidden. BeyondTechWorld is not blocked. Customer URL enforcement stays in the deployed middleware and was proved in the test suite, not by creating a live customer.
- Subscription notices are stored as platform rows and are not sent through a customer’s WhatsApp entitlement. None were created, because BeyondTechWorld has no subscription.
- Staff were not clicked through the ERP with a password. Authenticated pages returned 302 to login. Internal access was checked with the live tenant context and the module gate.
