# Beyond Cloud Phase 1F-LAUNCH — Public Signup

Public company signup is open. This did not add a new registration route. It turned on the Phase 1F onboarding flow that already passed the controlled production test.

Production commit: `4ad33b9f373afebe57f6126dfbe92c3f7a52e543`  
Signup confirmed open: `2026-10-02 21:07:01 UTC`

Beyond Cloud Validation Company remains SUSPENDED. It was not reactivated and not deleted.

## Backup

Taken before signup was opened. Restore verification matched the live database, and the restore copy was dropped.

| Item | Value |
| --- | --- |
| UTC timestamp | 2026-10-02 21:06:15 UTC |
| Database | `beyondtechworld_laravel` |
| Commit | `4ad33b9f373afebe57f6126dfbe92c3f7a52e543` |
| Path | `/var/backups/beyondtechworld/20261002-210615-phase1f-launch/database.sql.gz` |
| SHA-256 | `cd1e9ffa5b5f3aa4b0500362dd153499c3c636b89daca2e5e3a0e23080c849fb` |
| gzip | passed |
| Restore | products 472, customers 745, sales 31, quotations 26, payments 30, bookings 64, tenants 2, subscriptions 3, plans 4 |

## Signup switch

`CLOUD_PUBLIC_ONBOARDING` is true. A storage flag overrides that setting on each request, so signup can be closed without deploying code and without rebuilding config.

Verified in production:

1. Flag set to closed: `/cloud/register` showed “Company signup is not open yet.” and did not show the form.
2. Flag set back to open: the onboarding form returned.

Platform Admin can use **Close signup** / **Open signup** on the subscriptions admin screen. That writes the same flag and records an admin audit event on the internal company. Companies already created are not deleted.

`CLOUD_PAYMENTS_LIVE` remains false. `billing_sandbox` remains false.

## Health checks

Before opening, `/cloud/register` was closed. After opening:

| Check | Result |
| --- | --- |
| `GET /cloud/register` | HTTP 200, onboarding form |
| `GET /subscriptions` | HTTP 200 |
| `GET /login` | HTTP 200 |
| `GET /` | HTTP 200 |
| `GET /cloud/login` | HTTP 200 |
| `GET /c/beyondtechworld` | HTTP 404 |
| BeyondTechWorld | INTERNAL, ACTIVE, 0 subscriptions |
| Validation company | CUSTOMER, SUSPENDED |
| WhatsApp connection | still the existing Beyond connection, ACTIVE |
| Queue worker | running |
| `cloud:process-subscriptions` | `changed=0` |
| MAI | answered a general question; the test product was not mentioned |

The register form shows Messaging, Sales & Invoices, and Rentals at 5,000 with a 24 hour trial, taken from `cloud_plans`. Due today is 0. The page says the trial is free and does not ask for a card.

Trial and expiry sentences are configuration, not a PHP price constant:

- During trial: “Your selected services are available free during your trial.”
- After trial, while online payment is off: “Your free trial has ended. Your information remains available in read-only mode. Please contact BeyondTechWorld to activate or renew your subscription.”

“Payment Successful” is still shown only after the existing payment confirmation path. That path was not activated.

## Rate limiting and abuse controls

| Action | Limit |
| --- | --- |
| Company registration POST | 5 requests per 10 minutes |
| Registration page GET | 30 requests per minute |
| Company portal login | 10 requests per minute |
| Staff login | 10 requests per minute |
| Password reset request and confirm | 5 requests per 10 minutes |

There is no public slug-availability endpoint. The server assigns the slug. Reserved names include `admin`, `api`, `login`, `register`, `cloud`, `subscriptions`, and `beyondtechworld`, plus the other application routes already reserved in `CloudSlug`.

Duplicate submission still returns the company already created for that onboarding token. One introductory trial is still allowed per company and module. A public request cannot create an INTERNAL company or take the BeyondTechWorld slug. The registering account becomes OWNER through a membership. Global `role_id` stays 5. Platform Admin is not granted.

Email verification is not part of company signup. SMS verification was not added.

A new company sends a plain email to active platform administrators with the company name, type, owner email, selected modules, and created time. It does not include a password. Mail failure does not roll back the company. The subscriptions admin list already shows company, owner, created time, modules, trial state, trial end, and status.

## First registration

No new company registered during activation. Tenant count stayed at 2: BeyondTechWorld and the suspended validation company. The controlled test company was not used again.

## Ownership audit

Run immediately before opening signup:

- unowned = 0
- invalid = 0
- cross-tenant = 0

## Queue, MAI, WhatsApp, Beyond

- Queue worker process is up. Two WhatsApp jobs were already waiting. None were reserved. Failed jobs remained 13. No new failure was created by the launch.
- Scheduler command `cloud:process-subscriptions` changed nothing. The validation trials were not duplicated and no subscription became paid.
- MAI answered normally and did not surface the validation product.
- Beyond’s WaSender connection was not attached to another company.
- BeyondTechWorld stayed INTERNAL and ACTIVE with 0 subscriptions.

## Incidents

None. Signup was closed, opened, closed again to prove the kill switch, and left open. No tenant was created or deleted by those checks.

## Known limitations

- Online card and MoMo subscription payment is not activated. Renewal is by contacting BeyondTechWorld. A platform administrator activates a paid period only through the existing audited subscription screen.
- A Messaging trial does not connect WhatsApp. The messaging page says WhatsApp is not connected and that admin setup is required.
- SMS stays unavailable. It is not required to sign up.
- The full application scheduler was not run, so operational reminders were not sent as part of this launch.
- There is no public email-verification step on company signup.
- Closing signup must set the flag to closed. Removing the flag falls back to the environment setting, which is now on.

## Required status

PUBLIC COMPANY SIGNUP: OPEN

SAAS ONBOARDING: LIVE

24-HOUR TRIALS: LIVE

PAYMENT PROVIDER PRODUCTION ACTIVATION: NOT VALIDATED

ONLINE RENEWAL: NOT AVAILABLE

WHATSAPP CUSTOMER SELF-CONNECTION: ADMIN SETUP REQUIRED

SMS: OPTIONAL / DEFERRED

SMS PRODUCTION SENDING: DISABLED

TENANT ISOLATION: PASS

OWNERSHIP AUDIT: PASS

MAI: PASS

WHATSAPP REGRESSION: PASS

BEYONDTECHWORLD: HEALTHY
