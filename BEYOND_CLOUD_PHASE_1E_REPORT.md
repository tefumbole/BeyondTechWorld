# Beyond Cloud — Phase 1E report

Status: **PHASE 1E IMPLEMENTED AND TESTED. NOT DEPLOYED.**

Public company signup stays closed. Phase 1F was not started. No external production tenant was created. Ownership columns were not changed to NOT NULL. SMS was not added.

Production remains on `50c81a9d03a6ec1184d65d9a5bc306a18d46e445` until a later controlled deployment.

## Audit

See `BEYOND_CLOUD_PHASE_1E_AUDIT.md`.

The existing catalog, plans, subscriptions, phone trial claims, internal entitlements, and Campay/Stripe checkout were kept. Prices were not rewritten. Messaging stays 5,000 XAF. Every current plan trial stays 24 HOUR. Those values are read from `cloud_plans`.

## Models reused

`CloudModule`, `CloudPlan`, `CloudSubscription`, `CloudSubscriptionPayment`, `CloudTrialClaim`, `CloudPaymentMethod`, `CloudInternalEntitlement`, `CloudTenantSetting`.

Added beside them, not instead of them:

| Table | Purpose |
| --- | --- |
| `cloud_module_trials` | Introductory and admin trial history per company and module |
| `cloud_subscription_events` | Audit log. No payment secrets |
| `cloud_billing_events` | One row per provider event id |
| `cloud_subscription_notices` | Reminder and payment-due rows |

`cloud_subscriptions.cancel_at_period_end` was added. Period fields already present are the source of truth: `trial_started_at`, `trial_ends_at`, `current_period_start`, `current_period_end`.

## Subscription state machine

`CloudSubscriptionService` is the only writer of subscription status.

| Status | When | Access |
| --- | --- | --- |
| TRIALING | Introductory or admin trial, before `trial_ends_at` | Read and write |
| ACTIVE | Provider-confirmed payment, before `current_period_end` | Read and write |
| PAST_DUE | Paid period has ended and cancel-at-period-end is off | Read only |
| EXPIRED | Trial ended with no payment, or past-due after a configured grace | Read only |
| CANCELLED | Cancel now, or cancel-at-period-end once the period ends | Read only |
| SUSPENDED | Platform admin | Read only |

Historical rows are not deleted.

Grace is `config('cloud.grace_hours')`, default **0**. No grace length was approved. With 0, a paid period ends as `PAST_DUE` (read-only) so a later payment can recover it. It is not auto-expired. A trial that ends unpaid becomes `EXPIRED` immediately and is also read-only.

Cancel now and cancel at period end are separate. Cancel at period end keeps `ACTIVE` until `current_period_end`.

## 24-hour trial

The end time is `trial_started_at` plus the plan's `trial_value` and `trial_unit`. The application does not add a hardcoded 24 hours. The trial starts when the company subscribes to that module, not when someone opens `/subscriptions`.

The same company and module cannot take another introductory trial after cancel, resubscribe, or a new plan on that module. The phone claim from Phase 1B still blocks the same phone on another company. A platform admin can grant another trial. That grant is an `ADMIN` history row and an `ADMIN_OVERRIDE` event. It does not delete the introductory row.

## Module access

`CloudModuleAccessService` answers `canAccess`, `canRead`, `canWrite`, `status`, `reason`, `trialEndsAt`, and `subscriptionEndsAt`.

INTERNAL BeyondTechWorld uses `CloudInternalEntitlementPolicy` for WhatsApp Hub, Sales & Invoices, and Rentals. Zero subscriptions. No trial, renewal, or expiry. Messaging capability is satisfied by the WhatsApp Hub entitlement.

CUSTOMER companies are per module:

| Capability | Modules that satisfy it |
| --- | --- |
| messaging | `WHATSAPP_HUB` or `MESSAGING` |
| sales | `SALES_INVOICES` only |
| rentals | `RENTALS` only |
| catalog (products, customers) | Sales or Rentals |
| quotations | Sales or Rentals |

Catalog and quotations are shared because those screens use the same product, customer, and quotation rows. Subscribing to Messaging does not open Sales or Rentals. Subscribing to Rentals does not open Sales invoices or payments.

## Enforcement

`EnforceCloudModule` runs on the web stack after the company is resolved. It applies only when the active company is `CUSTOMER`. INTERNAL BeyondTechWorld is not blocked. Unmapped paths, including `/cloud/*` and `/subscriptions`, stay open so an expired module cannot lock the company out of renewal.

Mapped paths include sales, quotations, invoices, payments, products, customers, bookings, rentals, announcements, and `/admin/whatsapp`. GET is read. POST and create/edit screens are write. A manual URL and a JSON post use the same rule. Denied responses are HTTP 403, not an empty catalogue.

MAI tool names for a module are removed from the prompt when that CUSTOMER company cannot use them. `create_rental_quotation` and `search_rental_products` return `module_not_entitled` for Messaging-only, including a prompt that says to ignore subscriptions.

Outbound WhatsApp (`sendTextRaw`, documents, images, polls, group sends) returns `messaging_not_entitled` without throwing when the active company is a CUSTOMER without a writable messaging capability. Inbound webhook storage is unchanged. `ProcessAssistantTurn` and `SendWaAnnouncementBatchJob` check again at execution time, so a job queued during a trial does not send after expiry. A missing company context, and an INTERNAL company, are not blocked. That keeps Beyond's own sending intact.

Subscription reminder rows are stored in `cloud_subscription_notices`. They are not sent through the customer's paid WhatsApp entitlement, so an expired Messaging plan cannot block or loop a platform notice. No SMS provider was added.

## Billing

`CloudBillingProviderInterface` separates subscription billing from ERP customer payments. Campay (MoMo) and Stripe (VISA) remain the live checkout implementations already in `CloudCheckoutService`. PayPal and PawaPay stay on ERP customer charges. They are not Cloud subscription providers.

A Pay click creates a `PENDING` `CloudSubscriptionPayment` for the quoted plan amount and currency. It does not activate the subscription. The amount is taken from the subscription quote or the plan row, not from the browser. Currency is the plan currency, XAF, not `general_settings`.

`CloudSubscriptionService::confirmPayment` activates or extends one period only when status is paid, the tenant matches, and the amount and currency match the payment row. A wrong amount is `RECONCILE` and does not activate. A failed status does not activate. The same provider event id is stored once and does not extend the period again. A second confirmation of an already paid row does not extend it either.

Sandbox webhooks at `POST /cloud/billing/webhook/sandbox` work only when `cloud.billing_sandbox` is true. The default is false. Live Stripe and Campay webhook URLs return **501**.

**PAYMENT PROVIDER PRODUCTION ACTIVATION NOT YET VALIDATED.**

Successful tests used the sandbox provider. No live payment was marked successful.

Subscription charges stay on `cloud_subscription_payments`. They are not inserted into `sales` or `payments`.

## Scheduler and portal

`cloud:process-subscriptions` runs every five minutes. Running it twice does not repeat a transition. It expires trials, closes cancel-at-period-end subscriptions, moves finished paid periods to `PAST_DUE`, and records trial-ending and subscription-expiring notices.

`/subscriptions` still reads plan name, price, currency, billing interval, and trial from the database. The company portal home shows status, trial end in the company timezone, a server-calculated countdown, period end, and a Renew link. The countdown is display only. Access uses the stored timestamps.

Platform admin routes, limited to role_id 1 or 2, can grant a trial, extend, suspend, reactivate, and cancel. Each writes an audit event.

## Tests

Sqlite tests, not production:

| Suite | Result |
| --- | --- |
| `CloudSubscriptionEnforcementTest` | 10 tests, 111 assertions |
| `CloudPortalTest` | 3 tests, 43 assertions |
| `CloudInternalTenantTest` | 3 tests, 36 assertions |
| `CloudPlatformFoundationTest` | 7 tests, 99 assertions |
| `CloudTenantIsolationTest` | 9 tests, 38 assertions |

Covered: 24-hour trial end from the plan, repeat introductory trial denied, admin grant allowed, trial expiry read-only, scheduler idempotent, module matrix for Messaging, Sales, Rentals, and combinations, URL and JSON deny, INTERNAL access with zero subscriptions, MAI rental tool hidden and denied, outbound send re-checked after expiry, failed payment, wrong amount, wrong tenant, duplicate webhook, past-due read-only, signup still closed, Phase 1D isolation still holds, ownership audit command exits 0 on the test database.

The Alpha company in the isolation test is given an active Rentals period so the product search still runs. It returns only Alpha products.

## Known limitations

- Live Campay and Stripe subscription webhooks are not activated.
- Grace longer than 0 has no approved value.
- ERP menu items are not hidden. The server rejects the URL. The company portal shows trial, renew, and expired states.
- Routes that are not in the module map are not subscription-gated. Sales, rentals, catalog, quotations, and WhatsApp hub paths are.
- Reminder rows are stored. They are not delivered on WhatsApp in this phase.
- Authenticated clicks through the full ERP UI were not repeated here. Those screens need the production schema. Enforcement was tested on the middleware and services.
- `php artisan cloud:audit-ownership` was run on the test database only. Production was not contacted.

## Production deployment

**NOT DEPLOYED.**

Next step after approval: Phase 1E-PROD, a controlled production deployment. Phase 1F (public company onboarding) and the SMS hub stay later.
