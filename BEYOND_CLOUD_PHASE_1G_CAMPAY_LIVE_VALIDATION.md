# Beyond Cloud Phase 1G-LIVE — Campay validation

Date checked: 2026-10-02.

This phase did not deploy code, did not enable the public Pay button, and did not close public company signup. No real mobile-money charge was sent. No Campay secret is written here.

## Official documentation

Checked on 2026-10-02, before any Campay request:

- CamPay Python SDK 1.1.0, published by CamPay (`info@campay.net`): `https://github.com/CamPay/campay-python-sdk` and the source file `src/campay/sdk.py` on the `main` branch.
- The same README on `https://pypi.org/project/campay/`.
- CamPay’s own getting-started note: sandbox signup is `https://demo.campay.net/en/signup/` and live login is `https://www.campay.net/en/login/`. Blog date on that page: 4 December 2025. It points at the SDK above.
- CamPay’s Postman collection is linked by CamPay as `https://documenter.getpostman.com/view/2391374/T1LV8PVA`. On 2026-10-02 the public page did not return the endpoint bodies, so it was not used as the request contract.
- CamPay’s security blog (`https://blog.campay.net/securing-your-transactions-best-practices-with-campay/`, 5 December 2025) says to validate webhooks. It does not publish a signature algorithm.

`camerpay.biz` is a different product. Its HMAC webhook documentation was not applied to CamPay.

## Documented Campay contract

| Item | Official SDK behavior | Not stated by CamPay |
| --- | --- | --- |
| Authentication | `POST /api/token/` with JSON `username` and `password`. Later calls use `Authorization: Token`. | Token lifetime. The SDK requests a new token for each operation. |
| Demo host | `https://demo.campay.net` when environment is `DEV` | |
| Live host | `https://www.campay.net` when environment is `PROD` | |
| Payment link | `POST /api/get_payment_link/` | A signed webhook URL on this call |
| Direct collect | `POST /api/collect/` | |
| Status | `GET /api/transaction/{reference}/` | |
| Link fields | `amount` (string), `currency`, `description`, `external_reference`, `redirect_url`, `failure_redirect_url`, `from`, `first_name`, `last_name`, `email`, `payment_options` | |
| Phone | Country code included. Example shape `2376xxxxxxxx` | A published test number |
| Currency | Examples use `XAF` | A second currency for Cameroon collection |
| Operators | MTN and Orange. README examples name both, including USSD hints for MTN and Orange | A guarantee that both are enabled on this account |
| Status values | `PENDING`, `SUCCESSFUL`, `FAILED` | |
| Link response | HTTP 200 returns `link` and `reference`. The SDK labels that response `SUCCESSFUL` because the link was created | That label meaning the customer has paid |
| Amount | Sent as a string. Sample value `"5"` | A minimum amount |
| Callback security | Browser `redirect_url` and `failure_redirect_url` only, in this SDK | A webhook signature algorithm |

The SDK says a status read before the customer starts the payment can report that the transaction is not found. Polling in the blocking collect example waits 3 seconds between `PENDING` reads. No retry limit is published.

## What the live hosts returned

No Campay username, password, or token is present in the local application environment. Only empty placeholders exist in `laravel-app/.env.example`. Production credentials were not read and were not copied here.

Unauthenticated checks on 2026-10-02:

- `POST https://demo.campay.net/api/token/` with a JSON username and password that are not a real account: HTTP 400, `non_field_errors`: “Unable to log in with provided credentials.”
- The same call to `https://www.campay.net/api/token/`: HTTP 400 with the same key.
- `POST https://demo.campay.net/api/get_payment_link/` without a token: HTTP 401, “Authentication credentials were not provided.”
- `GET https://demo.campay.net/api/transaction/not-a-real-reference/` without a token: HTTP 403, body key `message`.

Those responses confirm the hosts and the token route. They do not confirm an account, XAF collection, or a transaction.

## Adapter comparison

The Phase 1G adapter did not match the official SDK:

- It sent a stored token and did not call `POST /api/token/` with the application username and password.
- `get_payment_link` omitted `redirect_url`, `failure_redirect_url`, `from`, `first_name`, `last_name`, `email`, and `payment_options`.
- The default host is the live host. A demo call must use `demo.campay.net`.
- A link response labeled `SUCCESSFUL` must not be treated as collected money.

The adapter now follows that contract. Amount and currency still come from the stored payment request. The reference stored is the reference Campay returns. None is invented. A payment-link `SUCCESSFUL` leaves the Cloud payment `PENDING` and does not activate the subscription. Status remains `GET /api/transaction/{reference}/`.

While `CLOUD_PAYMENTS_LIVE` is false, the adapter will not call `www.campay.net`. A call is allowed only to `demo.campay.net`, or when live payments are explicitly on. Live payments stay off.

Repeated Pay clicks for the same subscription, inside three minutes, do not open another live Campay request. Checkout and Pay routes are limited to 5 requests per 10 minutes. A failed payment is not reused; a later attempt creates a new payment row.

PHPUnit still uses an injected transport and does not call Campay. After the adapter change, these passed:

- `CloudBillingTest` — 15 tests, 96 assertions
- `CloudSubscriptionEnforcementTest` — 10 tests, 111 assertions
- `CloudOnboardingTest` — 9 tests, 124 assertions, including the sqlite ownership audit
- `CloudTenantIsolationTest` — 9 tests, 38 assertions
- `CloudPortalTest` — 3 tests, 42 assertions
- `WhatsAppWebhookTest` — 17 tests, 69 assertions

`php artisan cloud:audit-ownership` against the local MySQL login was not available. The onboarding test’s sqlite audit completed. Production was not contacted.

## Results that require an authenticated Campay call

Not run, because login could not be completed:

- Sandbox payment initiation and a Campay-issued reference
- XAF accepted on a real request
- Pending, success, and failed states from Campay
- Status lookup of a real reference
- Callback delivery
- Trial-to-paid period, active renewal, and expired or past-due restart against a Campay confirmation
- Receipt and billing history for a Campay payment
- One controlled real XAF mobile-money payment

Documented networks are MTN and Orange. Neither was charged. “Cameroon mobile money validated” does not apply.

Callback: the official SDK does not define a signed webhook. The safer rule stays in place. A browser redirect is not authority. When a callback exists, it may only identify the payment. The server must then read `GET /api/transaction/{reference}/` and match tenant, amount, currency, and reference before activation. No signature scheme was added.

Provider fees were not returned by any call.

## Security

No API secret was found in the application environment files that were scanned, and none was added to Git, this report, logs, or browser code. The public Pay button remains off because `CLOUD_PAYMENTS_LIVE` defaults to false.

## Stop

Sandbox login failed the gate. No real-money payment was attempted. Public payment activation remains Phase 1G-PROD and is not ready.

## Status

The request contract now matches the official SDK. `NEEDS CHANGES` means that match has not been confirmed with a logged-in sandbox response, so the adapter is not signed off.

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
