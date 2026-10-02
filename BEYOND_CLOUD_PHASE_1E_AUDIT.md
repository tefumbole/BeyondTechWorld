# Beyond Cloud — Phase 1E audit

Audit date: 2026-10-02. Production commit at the start of this phase: `50c81a9d03a6ec1184d65d9a5bc306a18d46e445`. This audit does not change production.

## What already exists

### Models and tables

| Piece | Location | Role |
| --- | --- | --- |
| `CloudModule` | `cloud_modules` | Global catalog. Codes: `WHATSAPP_HUB`, `SALES_INVOICES`, `RENTALS`, `MESSAGING`. |
| `CloudPlan` | `cloud_plans` | Price, currency, `trial_value`, `trial_unit`, `billing_interval`. Database is the price source. `firstOrCreate` does not overwrite a live price. |
| `CloudSubscription` | `cloud_subscriptions` | One company, one plan. Status constants already match the requested lifecycle: `TRIALING`, `ACTIVE`, `PAST_DUE`, `SUSPENDED`, `CANCELLED`, `EXPIRED`. |
| `CloudSubscriptionPayment` | `cloud_subscription_payments` | A charge row. `PENDING` / `PAID` / `FAILED`. This is separate from ERP `payments` (customer invoices). |
| `CloudTrialClaim` | `cloud_trial_claims` | One trial per normalized phone and module. |
| `CloudTenantSetting` | `cloud_tenant_settings` | Company text settings. Not subscription state. |
| `CloudInternalEntitlement` | `cloud_internal_entitlements` | Platform modules for an INTERNAL company. Not a subscription. |
| `CloudPaymentMethod` | `cloud_payment_methods` | `MOMO` → provider `campay`. `VISA` → provider `stripe`. |

Subscription columns already stored: `trial_started_at`, `trial_ends_at`, `current_period_start`, `current_period_end`, `quoted_price`, `quoted_currency`, `cancelled_at`, `suspended_at`.

Live plan configuration (do not change in this phase):

| Plan | Price | Trial |
| --- | --- | --- |
| WhatsApp Hub | 10,000 XAF | 24 HOUR |
| Sales & Invoices | 5,000 XAF | 24 HOUR |
| Rentals | 5,000 XAF | 24 HOUR |
| Messaging | 5,000 XAF | 24 HOUR |

`general_settings.currency` is the legacy ERP currency and is not the Cloud price source.

### Entitlements today

`CloudInternalEntitlementPolicy` grants BeyondTechWorld `WHATSAPP_HUB`, `SALES_INVOICES`, and `RENTALS` when those rows are enabled. It does not read `trial_ends_at` and does not create a payment. `CloudPortalService::startTrial` refuses an INTERNAL company.

Nothing in the ERP route stack calls that policy. A CUSTOMER company can still open Sales, Rentals, or WhatsApp by URL. MAI tools are tenant-scoped and are not module-scoped.

### Trial today

`startTrial` copies `plan.trial_value` and `plan.trial_unit` onto `trial_ends_at`. It does not hardcode 24 hours. It blocks a second active trial on the same plan, and a second trial for the same phone and module.

It does not record a tenant-level trial history that survives cancel. Cancelling is not implemented. A new company with a new phone can take another introductory trial. That part is the gap this phase closes for the same tenant and module.

### Payments today

`CloudCheckoutService` creates a `CloudSubscriptionPayment` from the plan price on the server, then opens Campay (MoMo) or Stripe Checkout (VISA). The browser return does not mark MoMo paid. VISA is marked paid only when Stripe `payment_status` is `paid` and the session id matches `provider_reference`.

Gaps:

- A second confirmed payment on an already `ACTIVE` subscription does not extend the period.
- The paid period is `addMonth()`, not the plan's billing interval in one place.
- There is no billing-event id, so a duplicate provider notification is safe only when the payment row is already `PAID`.
- Amount is not compared with the provider payload before activation.
- A payment row is not checked against a different tenant before activation, except the browser return route.
- No webhook endpoint.
- PayPal and PawaPay exist for ERP customer charges and funeral/booking flows. They are not Cloud subscription providers.

### Pages, routes, scheduler

- Public `GET /subscriptions` reads `cloud_plans` and `cloud_payment_methods`. Trial text uses `trial_value` and `trial_unit`. The billing label says "per month" instead of the plan interval.
- Company portal: `/cloud`, `/cloud/subscribe`, trial start, pay, settings. Public `/cloud/register` stays closed unless `cloud.public_onboarding` is true. Default is false.
- Admin: `/admin/subscriptions` for role_id 1 or 2, price edit only. No grant, extend, suspend, or audit log.
- Scheduler in `app/Console/Kernel.php` runs reminders, announcements, rentals, and WhatsApp pruning. Nothing expires a Cloud subscription.

### Module dependencies observed

Sales, quotations, invoices, and payments are one commercial module (`SALES_INVOICES`). Rentals, bookings, and rental quotations share tables with the rest of the ERP (products, customers, quotations). There is no separate rental-quotation type. Messaging (`MESSAGING`, 5,000 XAF) and WhatsApp Hub (`WHATSAPP_HUB`, 10,000 XAF) are separate plans that both operate WhatsApp outbound.

## What is missing

- One service that owns status transitions, renewal, cancel-now, and cancel-at-period-end.
- One service that answers `canRead` / `canWrite` for INTERNAL entitlements and CUSTOMER subscriptions.
- Tenant/module introductory-trial history that cancel, resubscribe, or plan switch cannot reset.
- Route and MAI enforcement for CUSTOMER companies.
- Outbound WhatsApp blocked when a CUSTOMER messaging entitlement is not writable, without refusing inbound webhook storage.
- Idempotent billing events, amount and tenant checks, and a provider interface separate from Campay/Stripe.
- Scheduler for trial end, period end, and reminder rows that are not sent through the customer's paid WhatsApp entitlement.
- Platform-admin override with an audit row.
- Configurable grace. No grace length has been approved.

## Decisions for the implementation

- Reuse the models above. Do not create a second plan or subscription table.
- Map existing period columns instead of adding duplicate start/end columns. Add `cancel_at_period_end` only.
- Grace hours default to 0 in `config/cloud.php`. A paid period that ends becomes `PAST_DUE` (read historical records, no writes) so a later payment can recover it. A trial that ends unpaid becomes `EXPIRED` with the same read-only rule. Data is not deleted.
- INTERNAL BeyondTechWorld keeps platform entitlements with zero subscriptions.
- Public company signup stays closed. No production deploy in this phase. No SMS providers.
