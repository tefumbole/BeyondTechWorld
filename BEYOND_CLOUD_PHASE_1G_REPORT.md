# Beyond Cloud Phase 1G — Report

Date: 2026-10-02. Local implementation and test validation only. Nothing in this phase was deployed. Production payments were not enabled. Public signup was not closed. SMS and WhatsApp self-connection were not changed.

Audit: `BEYOND_CLOUD_PHASE_1G_PAYMENT_AUDIT.md`.

## Selected provider

Campay, for Cameroon XAF mobile money (MTN and Orange).

Evidence checked 2026-10-02:

- Campay collect/status collection: `https://documenter.getpostman.com/view/2391374/T1LV8PVA`
- Campay WooCommerce plugin verifies a webhook JWT with the dashboard key and then re-reads transaction status.
- PawaPay official provider and signature docs were read and not selected, because this application has no PawaPay collect implementation.
- Stripe remains the coded card method. Its production webhook stays closed. A browser return to the site is not payment authority.
- PayPal was not selected.

Supported currency for plan billing: XAF, taken from `cloud_plans` / the stored quote. Supported customer methods in the catalog: MoMo through Campay, and VISA through Stripe. Only the Campay status path was implemented behind the billing interface for this phase. Stripe checkout code remains behind `CLOUD_PAYMENTS_LIVE`, which defaults to false.

Sandbox capability: Campay has a separate test host in its public docs. This phase did not call it. PHPUnit uses an injected transport and never calls `campay.net` or Stripe.

Webhook verification: activation uses the Campay transaction-status read for the stored provider reference. The posted body cannot set the amount, currency, or tenant. A signature algorithm was not invented. The WooCommerce plugin's JWT check is noted in the audit and is not treated as sufficient by itself. While sandbox mode is off, `POST /cloud/billing/webhook/campay` and the Stripe webhook still return HTTP 501.

## Implementation status

Implemented locally, not deployed:

- `CloudBillingCheckout` writes one payment request and one line per module. The total, currency, tenant, plans, and prices are a snapshot taken before any provider call. Browser amount, currency, and `cloud_tenant_id` are ignored.
- `CloudCampayBillingProvider` implements `CloudBillingProviderInterface`. Tests inject the HTTP transport. Live Campay and Stripe are not called unless `CLOUD_PAYMENTS_LIVE` is true. With both live payments and billing sandbox off, checkout throws before creating a charge and before any HTTP call.
- A Campay webhook in sandbox mode loads the payment by our internal reference, re-reads status, then calls `confirmPayment`. Pending does not activate. Failed does not activate. The same status event activates or extends once.
- Wrong amount or wrong currency sets `RECONCILE` and notifies platform admins. The subscription stays unchanged. Wrong reference or wrong tenant is denied and audited.
- Receipts and the tenant billing page show only that company's rows. A receipt is available only when status is `PAID`.
- Platform admin billing list filters by status, provider, and tenant.
- Manual activation is `ADMIN` confirmation, platform role 1 or 2 only, with method, reference, reason, actor, and the plan period. A company owner receives HTTP 403 from the self-activate route and cannot mark themselves paid.
- A recorded refund sets `REFUNDED` and does not delete the company, the subscription, or the period already granted. Campay's subscription reversal API was not available as an implemented provider feature, so refunds are an admin record only. No automatic removal of historical access.

## Business rules

Trial to paid: if the subscription is still `TRIALING` and `trial_ends_at` is in the future, the paid month starts at `trial_ends_at`. Status becomes `ACTIVE` immediately, so access is not removed during the remaining trial, and the paid month is not shortened.

Renewal: if status is `ACTIVE` or `SUSPENDED` and `current_period_end` is still in the future, the new month starts at that `current_period_end`. Remaining paid time is kept. `EXPIRED`, `PAST_DUE`, and an already ended trial start the paid month at confirmation time.

A failed or pending attempt does not suspend a trial or a paid period early. The scheduler still expires a trial or moves a paid period to `PAST_DUE` only when that period's own end time has passed. Running it again does not apply the same change twice.

Module access after `PAID` is still `CloudModuleAccessService`. There is no second entitlement switch. The internal BeyondTechWorld company cannot open a subscription checkout.

Mail failure after a confirmed payment is caught and does not roll the payment back.

## Tests

`tests/Feature/CloudBillingTest.php`: 14 tests, 85 assertions, passed. The transport is in-process. No real provider was called.

Covered: payment request without activation, browser return without activation, live calls refused while payments are disabled, Campay success once across repeated callbacks, pending, failed, wrong amount, wrong currency, wrong reference, wrong tenant, two-module bundle with a third module left on trial, pay before trial end, pay after trial expiry, renew an active period, restart a past-due period, admin manual activation, owner self-activate refused, refund keeps the period, tenant billing isolation, receipt only after success, admin filters, mail failure, scheduler expiry once, internal company refused, Campay and Stripe webhooks HTTP 501 when sandbox is off.

Re-run locally, all passed:

- `CloudSubscriptionEnforcementTest` — 10 tests, 111 assertions
- `CloudOnboardingTest` — 9 tests, 124 assertions, including `cloud:audit-ownership` on the test database
- `CloudTenantIsolationTest` — 9 tests, 38 assertions
- `CloudPortalTest` — 3 tests, 42 assertions
- `CloudPlatformFoundationTest` — 7 tests, 100 assertions
- `CloudInternalTenantTest` — 3 tests, 36 assertions
- `WhatsAppWebhookTest` — 17 tests, 69 assertions

`php artisan cloud:audit-ownership` against the local MySQL configuration was not run successfully: the configured database user is refused on localhost. That is an environment login failure, not an ownership result. The onboarding test's sqlite audit completed with exit code 0. Production was not contacted.

## Phase 1G-LIVE

Campay’s own SDK was checked on 2026-10-02. The adapter was corrected to that contract: token login, the official payment-link fields, and the demo host `demo.campay.net`. A payment-link response does not activate a subscription.

No local Campay username, password, or token is configured. Unauthenticated calls reached the demo and live token routes and were rejected. No sandbox payment, XAF collection, status lookup, or handset payment was completed. Real money was not charged. Details are in `BEYOND_CLOUD_PHASE_1G_CAMPAY_LIVE_VALIDATION.md`.

Public Pay stays off. This phase was not deployed.

## Known limitations

- The demo host answered, and login was rejected because no Campay application credentials are configured locally. No payment was created and no handset was charged.
- The official SDK does not publish a webhook signature. A browser redirect is not payment authority. Production webhooks remain closed.
- Stripe card checkout is not activated. There is no Stripe webhook signing secret in application config.
- Provider refund and chargeback APIs are not connected. An admin refund record does not delete data.
- This code is in the local working tree only.

## Required status

PHASE 1G ARCHITECTURE: PASS

SELECTED PAYMENT PROVIDER: Campay

PROVIDER ADAPTER: IMPLEMENTED

SANDBOX PAYMENT: NOT VALIDATED against Campay's sandbox (application sandbox and injected transport: validated)

WEBHOOK: VALIDATED IN TEST

XAF: VALIDATED IN TEST

MOBILE MONEY: NOT VALIDATED

SUBSCRIPTION ACTIVATION: VALIDATED IN TEST

RENEWAL: VALIDATED IN TEST

DUPLICATE WEBHOOK PROTECTION: PASS

TENANT BILLING ISOLATION: PASS

PRODUCTION PAYMENT: DISABLED

PUBLIC COMPANY SIGNUP: OPEN

SMS: OPTIONAL / DEFERRED

Stop. Production payment activation is a separate phase, PHASE 1G-PROD.

## Phase 1G-LIVE status

CAMPAY ACCOUNT: NOT ACTIVE

CAMPAY SANDBOX: NOT VALIDATED

CAMPAY ADAPTER: NEEDS CHANGES

XAF: NOT VALIDATED

CAMEROON MOBILE MONEY: NOT VALIDATED

TRANSACTION STATUS LOOKUP: NOT VALIDATED

CALLBACK: NOT VALIDATED

PENDING STATE: NOT VALIDATED

SUCCESS STATE: NOT VALIDATED

FAILED STATE: NOT VALIDATED

TRIAL → PAID: NOT VALIDATED

ACTIVE RENEWAL: NOT VALIDATED

DUPLICATE PROCESSING: PASS

RECEIPT: NOT VALIDATED

TENANT BILLING ISOLATION: PASS

REAL MONEY TEST: NOT RUN

PRODUCTION PAYMENT: DISABLED

PUBLIC COMPANY SIGNUP: OPEN

SMS: OPTIONAL / DEFERRED
