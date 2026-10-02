# Beyond Cloud Phase 1G — Payment audit

Date: 2026-10-02. No secrets are recorded here. Environment values were not printed.

This phase was developed locally. It was not deployed. Public company signup was not closed. Production payment flags were not changed.

## Two payment domains

These must stay separate.

| Domain | Who pays whom | Authoritative record |
| --- | --- | --- |
| ERP business payments | A tenant's customer pays that tenant for a sale, invoice, rental, or property bill | `payments`, sale payment status, property bill requests |
| Beyond Cloud subscription billing | A tenant pays BeyondTechWorld / Beyond Enterprise for a SaaS module | `cloud_subscription_payments` and `cloud_subscription_payment_items` |

A sale is the ERP invoice. `App\Payment` is tenant-scoped and is not the SaaS ledger. Wealth income is posted from a completed sale, not from a Cloud subscription. Property bill webhooks use their own HMAC header and their own secret. They are not Campay and they are not subscription activation.

MAI tools that read rent, bill, or customer payment history do not mark a Cloud subscription paid. No assistant tool was added that can confirm a provider payment or activate a subscription.

## Existing providers

| Provider | Where it is used | Packages | Subscription readiness on 2026-10-02 |
| --- | --- | --- | --- |
| Campay | ERP and Cloud MoMo collection. HTTP calls, no Campay Composer package. | none | Closest production collect path for Cameroon, XAF, MTN and Orange. Cloud webhook was HTTP 501. |
| Stripe | ERP card checkout, funeral pledge return, Cloud VISA checkout. | `stripe/stripe-php` ^7.57 | Card method exists. No webhook signing secret in `config/services.php`. Cloud webhook was HTTP 501. XAF is a zero-decimal currency, so `unit_amount` is the whole amount. |
| PayPal | ERP sale return routes `sales/paypalSuccess` and `paypalPaymentSuccess`. | `srmklive/paypal` ^3.0 | Not a Cameroon XAF mobile-money subscription path. |
| PawaPay | Config keys only. No subscription adapter in this application. | none | Official docs support MTN and Orange in Cameroon and XAF, but this ERP does not collect subscriptions with it. |

Config keys, names only:

- Stripe: `STRIPE_KEY`, `STRIPE_SECRET`
- Campay: `CAMPAY_TOKEN` or `MOMO_TOKEN`, `CAMPAY_USERNAME`, `CAMPAY_PASSWORD`, `CAMPAY_APP_ID`, `CAMPAY_WEBHOOK_SECRET`, `CAMPAY_BASE_URL` (default host `https://www.campay.net/api`)
- PawaPay: `PAWAPAY_API_TOKEN` or `PAWAPAY_TOKEN`, username, password, webhook secret, environment, live base URL, checkout, deposit, payout, and refund callback URL keys

`CLOUD_PAYMENTS_LIVE` defaults to false. `CLOUD_BILLING_SANDBOX` defaults to false.

## Existing Cloud billing before this phase

Phase 1E already had a platform payment table, not an ERP sale payment.

- Table `cloud_subscription_payments`: tenant, subscription, method, amount, currency, provider, provider reference, status, paid time.
- Status constants: `PENDING`, `PAID`, `FAILED`, `RECONCILE`.
- Table `cloud_billing_events`: unique `(provider, event_id)` so the same provider event extends a period once.
- `CloudSubscriptionService::confirmPayment` rejects a mismatched tenant, a mismatched amount or currency, and a non-paid status. A second `PAID` result does not extend the period again.
- `CloudBillingProviderInterface` existed, but checkout called Campay and Stripe directly.
- `POST /cloud/billing/webhook/sandbox` runs only when billing sandbox is on. Stripe and Campay webhooks returned HTTP 501.
- The subscribe screen hides Pay unless `payments_live` is true. The copy tells the customer that online renewal is not available yet.
- A browser return used to be able to activate a VISA payment after the server retrieved a Stripe session. That return is no longer payment authority.

## Provider evidence checked on 2026-10-02

Campay collect and status, from the public Postman collection and the Campay WooCommerce plugin:

- Collection: `https://documenter.getpostman.com/view/2391374/T1LV8PVA`
- Amounts are collected in XAF. Documented statuses include `PENDING`, `SUCCESSFUL`, and `FAILED`. Operators include MTN and Orange.
- The transaction-status read is the authoritative check. A webhook body includes reference, status, external reference, amount, operator, and a signature.
- The Campay WooCommerce plugin verifies that signature as a JWT (HS256) with the dashboard webhook key, and then re-checks transaction status. The Postman page retrieved on this date did not publish the signature algorithm itself.

PawaPay, from official docs:

- Providers: `https://docs.pawapay.io/v2/docs/providers` — `MTN_MOMO_CMR` and `ORANGE_CMR`, currency XAF, no decimals.
- Signatures: `https://docs.pawapay.io/v2/docs/signatures` — RFC 9421 style headers (`Signature`, `Signature-Input`, `Signature-Date`, `Content-Digest`), `ecdsa-p256-sha256`, public key from their API. IP allowlists are an optional network control, not the only security control.

Stripe remains available for cards. It is not the selected Cameroon mobile-money path. PayPal is not selected. PawaPay is not implemented in this phase because the application has no production collect flow for it, even though its signature documentation is clear.

## Selected provider

Campay is the selected mobile-money provider for Beyond Cloud subscription checkout.

Reason: it is the provider this install already uses to collect XAF from MTN and Orange, it has a transaction-status API that can confirm a payment without trusting the browser, and the plan currency is already XAF. The choice is not “code exists”, alone. The status API plus XAF and mobile-money coverage is the reason. Stripe stays the card method in code, with its production webhook left closed.

## What was missing

- An immutable checkout snapshot and per-module line items.
- A bundle total calculated on the server.
- A provider adapter that checkout actually uses.
- Activation only after a provider status read, not after a redirect or a browser reference.
- A paid period that starts after a remaining trial instead of replacing it.
- A tenant billing history and a receipt that exists only after success.
- A platform billing list.
- A recorded difference between provider confirmation and admin manual confirmation.
- A hard stop so a Pay request cannot call Campay or Stripe while `CLOUD_PAYMENTS_LIVE` is false.

## State mapping

The application keeps the existing status constants. They map to the requested lifecycle as follows.

| Requested state | Stored status |
| --- | --- |
| CREATED, PENDING, PROCESSING | `PENDING` |
| SUCCEEDED | `PAID` |
| FAILED, CANCELLED | `FAILED` |
| EXPIRED | left `PENDING` until a later status read; the subscription itself uses its own expiry |
| REQUIRES_REVIEW | `RECONCILE` |
| REFUNDED | `REFUNDED` |

Confirmation source is separate: `PROVIDER` or `ADMIN`.

## Security controls already present

- Tenant comes from the signed-in company membership, not from a posted `cloud_tenant_id`.
- Amount and currency are copied from the stored plan quote.
- Billing events are unique per provider event id.
- Wrong tenant does not change the payment to paid.
- Wrong amount or currency sets `RECONCILE` and does not activate.
- Sandbox webhooks are refused unless sandbox mode is on.
- Secrets stay in environment config. They are not written to the database by this phase, not sent to the browser, and not copied into this report.
