# Beyond Cloud Phase 1F-LIVE — Controlled Customer Onboarding

Production validation of one CUSTOMER company through the real onboarding services. Public company signup was not opened.

Production HEAD after validation: `ecbac78c`  
Onboarding submission ran on: `68abe89d`  
Gate removed: `33f019f0`  
Gate command retired: `ecbac78c`

Server clock is UTC+1.

## Controlled-access mechanism

A temporary onboarding gate was used only for this test.

- The token was random, stored as a SHA-256 cache key, and expired.
- It was written to a mode-600 file on the server and was not printed or committed.
- The public registration switch stayed off.
- A normal visit to `/cloud/register` showed “Company signup is not open yet.” and did not include a validation token.
- The same `CloudOnboardingService::register` path used by public signup created the company. No onboarding rows were inserted by hand.
- After the test, the controller stopped accepting the gate, and `php artisan cloud:onboarding-gate` now refuses to write a token.
- A later POST that sent a validation token and the gate header created no user and no company.

## Test company

| Field | Value |
| --- | --- |
| Name | Beyond Cloud Validation Company |
| Slug | `beyond-cloud-validation-company` |
| Type | CUSTOMER |
| Status during the test | ACTIVE |
| Final status | SUSPENDED |
| Currency | XAF |
| Timezone | Africa/Douala |
| System name | Beyond Cloud Validation Company |

It is not INTERNAL.

## Owner membership

| Field | Value |
| --- | --- |
| Account | `cloud-validation@beyondcompanyltd.com` |
| Name | Cloud Validation |
| Global `role_id` | 5 |
| Membership | one row, company = test company, role = OWNER, status = ACTIVE |
| BeyondTechWorld membership | none |
| Platform Admin | no |

Ownership was not created by changing the global role.

## Modules selected

Submitted on the onboarding form:

- Messaging
- Sales & Invoices
- Rentals

## Pricing source

Quoted prices were stored from `cloud_plans`, not from the browser.

| Module | Plan price | Trial |
| --- | --- | --- |
| Sales & Invoices | 5000.00 XAF | 24 HOUR |
| Rentals | 5000.00 XAF | 24 HOUR |
| Messaging | 5000.00 XAF | 24 HOUR |

Due today was 0 XAF. No payment screen was used. No card, Campay, MoMo, or Stripe details were entered.

Subscription payment rows for this company: 0.  
Paid `ACTIVE` subscriptions for this company: 0.

## Trial timestamps

All three subscriptions:

- status `TRIALING`
- start `2026-10-02 21:44:58`
- end `2026-10-03 21:44:58`
- duration 1440 minutes (24 hours)

A second `startTrial` for Messaging returned the same subscription id and the same end time. The subscription count stayed at 3.

## Duplicate submission

The same onboarding token was posted again with a fresh CSRF token while the short completion cache was still valid.

- HTTP 302 to `/cloud`
- companies named Beyond Cloud Validation Company: 1
- memberships: 1
- subscriptions: 3

An earlier replay with the CSRF token from before login returned HTTP 419 and also created nothing.

A separate logged-out POST, with no valid gate, for a different company name created no user and no tenant.

## Branding

Saved through `/cloud/settings` while signed in as the test owner:

- system name: Beyond Cloud Validation Company
- summary: Controlled production validation tenant. Not a public customer.
- services: Messaging, Sales and Invoices, Rentals. Test only.
- a test logo

BeyondTechWorld after that save:

- system name: Beyond Tech World
- legal name: Beyond Enterprise
- type INTERNAL, status ACTIVE

## Public page

Logged out, `GET /c/beyond-cloud-validation-company` returned HTTP 200.

- The test company name and validation summary were shown.
- Beyond speakers and Beyond Enterprise were not shown.
- The setup checklist was not shown.
- A follow-up `GET /cloud` with that visitor cookie redirected to the staff login. The visitor was not signed in and did not become a member.

`GET /c/beyondtechworld` remained HTTP 404.

## Product isolation

Created under the test company context:

- `TEST CLOUD PRODUCT - DELETE/ARCHIVE AFTER VALIDATION`
- code `TEST-CLOUD-VAL-001`
- `cloud_tenant_id` = test company
- day rental price set

The test owner’s product query returned 1 row. Beyond’s product query still returned 471. A direct find of Beyond product id 1 under the test context returned nothing. A forced save of that Beyond product was rejected and then rolled back. The Beyond product name was unchanged.

## Customer isolation

Created:

- `TEST CLOUD CUSTOMER - VALIDATION ONLY`
- `cloud_tenant_id` = test company

The test context sees 1 customer and does not find Beyond customer id 1.

The customer row uses a shared customer-group id because that column is required and customer groups are not tenant-owned. The customer record itself is tenant-owned.

## Sales and quotation

| Record | State | Ownership |
| --- | --- | --- |
| Sale `TEST-CLOUD-VAL-S` | Draft (`sale_status` 3), paid amount 0 | test company |
| Quotation `TEST-CLOUD-VAL-Q` | Draft (`quotation_status` 1), note says do not send | test company, test customer, test product’s warehouse |

No wealth-income row was created for the draft sale. The quotation was not sent.

Quotations require a biller. Billers are not tenant-scoped, so an inactive biller named `TEST CLOUD BILLER` was created for this draft. Beyond’s biller was not modified. The quotation was not emailed or sent on WhatsApp.

Sales entitlement while the trial was open: write allowed. Beyond sale id 1 was not visible in the test context. Opening Beyond invoice URL as the test owner redirected away and did not render the invoice.

## Rental test

The test product is the rental item, with a day rate. No booking was created. Beyond bookings stayed at 64 and booking id 1 was not visible in the test context.

## Messaging state

On `/cloud/messaging` for the test company:

- Messaging: Active
- WhatsApp: Not connected
- Admin setup required
- SMS: Not available

No WhatsApp connection row exists for the test company. Beyond’s connection remains the existing active connection on BeyondTechWorld. It was not attached to the test company and no message was sent.

## WhatsApp isolation

- Test company connections: 0
- Beyond connection still belongs to BeyondTechWorld and is still active
- A test contact was created with a phone that already exists on BeyondTechWorld
- That test contact has 0 conversations
- Beyond’s matching contact still has its original conversation
- Beyond conversation id 1 is not visible in the test context
- The messaging page does not show a Wasender credential

The customer ERP WhatsApp screen redirected away and did not render the conversation.

## MAI tenant test

Inside the test company, product search for “TEST CLOUD PRODUCT” returned only that product, including when a Beyond company id was supplied in the tool parameters. The tool drops that parameter.

Search for “speaker” and for “BeyondTechWorld” returned no products.

Back in BeyondTechWorld, “speaker” returned 8 products and did not include the test product. A general question, “What is two plus two?”, received a normal answer that two plus two equals four, and that answer did not mention the test product.

## IDOR tests

Under the test company context, finds for these Beyond ids returned nothing:

- product 1
- customer 1
- sale 1
- quotation 1
- payment 1
- booking 1
- WhatsApp conversation 1

No Beyond row was modified.

HTTP notes for the portal owner (`role_id` 5):

- Beyond sale invoice redirected to the sales list and did not render Beyond data
- Beyond quotation URL redirected to the quotation list
- Product show returned HTTP 500 because `ProductController::show` does not exist. The error page did not include the Beyond product name
- The test sale invoice returned HTTP 500 on a missing ERP permission variable before the invoice view. It did not show Beyond data
- Admin subscription, WhatsApp, and application-document URLs redirected away

## Company-switch attack

The test owner has only the test membership. `POST /cloud/company` with BeyondTechWorld’s id returned HTTP 403 Forbidden.

`GET /cloud?cloud_tenant_id=1` while signed in as the test owner still showed Beyond Cloud Validation Company.

## Trial expiry result

The production clock and the subscription rows were not changed.

`CloudModuleAccessService::decision` was called with a time one minute after each trial end:

- level `read`
- reason `trial_ended`
- writes denied for Messaging, Sales & Invoices, and Rentals
- reads still allowed

Immediately afterward, live writes were still allowed and the rows were still `TRIALING` with the original end time.

## Suspension result

A platform administrator (`role_id` 1) used `CloudAdminController`:

1. Suspend: test company `SUSPENDED`, sales writes denied with `company_suspended`, reads still allowed. BeyondTechWorld stayed INTERNAL and ACTIVE, and its sales write stayed allowed.
2. Reactivate: test company `ACTIVE`, Messaging write allowed again.
3. Final suspend: test company left `SUSPENDED`.

Three `ADMIN_OVERRIDE` events were recorded for the test company. BeyondTechWorld was not suspended. Records were not deleted.

## Platform admin result

The subscription admin screen, rendered for the platform administrator, showed:

- Beyond Cloud Validation Company
- CUSTOMER
- the owner account
- TRIALING
- Messaging, Sales, and Rentals

It did not show a Wasender API key or a Stripe secret.

No extra free trial was granted.

## Validation-gate removal

- The register page opens only when public onboarding is enabled.
- `public_onboarding` is false.
- `payments_live` is false.
- `billing_sandbox` is false.
- `cloud:onboarding-gate` prints “The temporary onboarding gate has been removed.” and does not write a file.
- Ordinary `GET /cloud/register` still says “Company signup is not open yet.”
- A POST with a validation token created nothing.

## Test tenant final state

SUSPENDED CUSTOMER. Not deleted.

Kept for audit:

- owner membership
- three introductory trials
- test product, customer, draft quotation, draft sale
- test contact
- inactive test biller
- branding and logo
- suspension events

## Ownership audit

`php artisan cloud:audit-ownership`

- unowned = 0
- invalid tenant = 0
- cross-tenant links = 0

Totals include the test rows: products 472, customers 745, sales 31, quotations 26, contacts 2332. Conversations stayed 181. Bookings stayed 64. Payments stayed 30.

## Beyond regression

BeyondTechWorld:

- type INTERNAL
- status ACTIVE
- subscriptions 0
- platform entitlements unchanged
- own product list still 471 under its company context
- sales, rentals, WhatsApp, and MAI still available
- WhatsApp connection still on this company and still active

## WhatsApp regression

No test connection was created. No message was sent through Beyond’s session. The two queued WhatsApp jobs were already present at 20:52, before this onboarding, and do not refer to the test company.

## MAI regression

Beyond speaker search returned 8 products and not the test product. The general arithmetic question answered normally and did not mention the test product.

## Queue and scheduler

Queue after the test:

- 2 jobs, both queue `whatsapp`, neither reserved
- created before onboarding
- no SMS or Infobip payload
- `failed_jobs` still 13

`cloud:process-subscriptions` was run twice. Both runs reported `changed=0`. The three test trials stayed `TRIALING` with the same end time. No paid subscription became active.

The full application scheduler was not run. That command also sends operational reminders and announcements, which this test was not allowed to trigger.

## Payment status

PAYMENT PROVIDER PRODUCTION ACTIVATION: NOT VALIDATED

No test action created a paid subscription.

## SMS status

SMS: OPTIONAL / DEFERRED  
SMS PRODUCTION SENDING: DISABLED  
INFOBIP: DEFERRED

SMS was not part of this pass. The customer messaging page says SMS is not available.

## Security log review

From 21:40 through 21:59, production log matches:

- MissingCloudTenant: 0
- cross-tenant: 0
- SMS / Infobip: 0
- Stripe / Campay / payment: 0

Earlier in the same evening, one signup insert failed before any row was committed because `users.is_deleted` had no default. That was fixed before the successful company was created. The database error was not shown again. Role-5 requests to ERP product and sale screens logged missing-method and missing-permission errors and did not render another company’s records.

A route-inspection error for `RouteServiceProvider::HOME` from the earlier deploy window was not repeated during this customer test.

## Known limitations

- The portal checklist still shows “To do” for products, customers, and quotations. Those flags are not calculated from the rows that were created.
- The portal owner is `role_id` 5. ERP product, sale, and booking screens are not the customer portal and return HTTP 500 or a redirect for that role. Isolation for those records was proven by the company scope and by invoice requests that did not render Beyond data.
- An inactive global biller row exists so the test quotation could satisfy `biller_id`. It is named as a test biller and is inactive.
- Trial expiry was evaluated by asking the access service for a future time. The clock and the trial rows were not edited.
- The full scheduler was not run, so reminder delivery was not exercised.
- With only one active WhatsApp connection, inbound routing still resolves that connection to BeyondTechWorld. The test company has no connection and was not given a way to send.

## Required status

CONTROLLED CUSTOMER ONBOARDING: PASS

CUSTOMER TENANT CREATION: PASS

OWNER MEMBERSHIP: PASS

24-HOUR TRIAL CREATION: PASS

DUPLICATE SUBMISSION PROTECTION: PASS

TENANT DATA ISOLATION: PASS

PUBLIC TENANT PAGE: PASS

MAI CUSTOMER TENANT ISOLATION: PASS

WHATSAPP TENANT ISOLATION: PASS

TRIAL EXPIRY PRODUCTION SIMULATION: PASS

PUBLIC COMPANY SIGNUP: CLOSED

PAYMENT PROVIDER PRODUCTION ACTIVATION: NOT VALIDATED

SMS: OPTIONAL / DEFERRED

OWNERSHIP AUDIT: PASS

BEYONDTECHWORLD REGRESSION: PASS

Public signup was not opened.
