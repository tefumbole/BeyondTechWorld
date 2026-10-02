# Beyond Cloud — Phase 1F report

Phase 1F is implemented and tested locally. It is not deployed. Public company signup stays closed on production.

## Status

PUBLIC COMPANY SIGNUP: TEST ONLY

PAYMENT PROVIDER PRODUCTION ACTIVATION: NOT VALIDATED

WHATSAPP CUSTOMER SELF-CONNECTION: ADMIN SETUP REQUIRED

Production deployment: NOT DEPLOYED. Production remains `f8c9e0be95ff635512e8650617a3a34732d29cf5`. `config('cloud.public_onboarding')` defaults to false. `config('cloud.payments_live')` defaults to false. Stripe and Campay subscription webhooks are unchanged and still return HTTP 501.

## Audit

`BEYOND_CLOUD_PHASE_1F_AUDIT.md` was written before the onboarding code. The subscriptions page, closed `/cloud/register`, `CloudTenant`, memberships, plans, `CloudSubscriptionService::startTrial`, `CloudModuleAccessService`, `CloudTenantContext`, the existing user table, company settings, and the company switch were reused. A second login system was not added.

`BEYOND_CLOUD_WHATSAPP_PROVIDER_ONBOARDING_AUDIT.md` records the current Wasender setup: one API key and one session id in configuration, one ACTIVE connection for BeyondTechWorld, and no proven customer session, subaccount, or QR flow. Customer self-connection was not invented.

## Onboarding flow

`/subscriptions` links to Build Your Own Company. Prices and the 24-hour trial are printed from `cloud_plans`. The page says online subscription payment is being activated.

When `cloud.public_onboarding` is true, `/cloud/register` lets the visitor pick Messaging, Sales & Invoices, and Rentals. Each card shows the plan name, description, monthly price, and trial from the database. The browser total is labeled as a preview. The server quotes the same plans again and ignores a browser total. Due today is 0. There is no card, MoMo, or Stripe field, and no Pay Now button.

When the flag is false, the same URL says company signup is not open yet and creates nothing. That is the production behavior until a later approval.

## Account and company

The form collects first name, last name, email, phone, password, and confirmation, plus company name and optional system name, legal name, company phone, company email, country, city, address, timezone, and currency. Blank currency becomes XAF. Blank timezone becomes Africa/Douala. The visitor can choose USD, EUR, NGN, GHS, Africa/Lagos, or UTC.

`CloudOnboardingService` creates the user with role_id 5, a CUSTOMER tenant in ACTIVE status, and an OWNER membership. A posted `type=INTERNAL` or `role_id=1` is ignored. Platform admin is not created. Ownership is the membership, not `users.role_id`.

An email that already belongs to a user is sent to the company login. A second user is not created. After login, Add another company creates a second CUSTOMER tenant for that same user.

## Slug protection

Slugs come from the company name. `Acme Events` becomes `acme-events`. A collision becomes `acme-events-2`. Reserved words, including `admin`, `api`, `cloud`, `subscriptions`, `login`, `register`, `beyondtechworld`, `c`, `products`, `sales`, and `rentals`, are not assigned. `BeyondTechWorld` does not receive the slug `beyondtechworld`.

## Trials

Each selected public module is started with `CloudSubscriptionService::startTrial`. The registration code does not calculate the end date. The plan row supplies 24 hours. One introductory trial per company and module, and one trial per phone and module, still apply. A second company on the same phone and the same module rolls back. A request that selects only WhatsApp Hub is rejected, so the public form cannot self-select the 10,000 XAF plan. Zero modules are rejected.

An already-open trial now returns that subscription instead of throwing. A finished introductory trial is still blocked.

## Transaction and duplicate posts

User, tenant, owner membership, settings, and trials commit together. A failed trial rolls the company back. The form carries a one-time token. A repeated POST of the same token returns the company already created. It does not create a second tenant, membership, or trial. Public registration is limited to 5 posts per 10 minutes. There is no new captcha package.

## Branding, landing, dashboard

The owner can upload a JPG, PNG, or WebP logo under 2 MB. The file is checked with `getimagesize` and stored as `storage/app/cloud-tenants/{uuid}/branding/logo.png` (or jpg/webp). The original filename is not the storage path.

`/c/{slug}` shows a CUSTOMER company's name, logo, description, and contact details. It does not sign the visitor in and does not change `cloud_active_tenant_id`. An INTERNAL slug, including `beyondtechworld`, is not served there. The page does not list products.

The company home shows the company name, trial status, a countdown from the server timestamp, the selected modules, and a setup list limited to those modules. It states that sample business records are not added. No Beyond products, customers, sales, quotations, bookings, or WhatsApp contacts are copied.

Messaging setup says the trial can be active while WhatsApp is not connected, and that admin setup is required. It does not read the Beyond API key or session id, and it does not create a connection row.

## Company switch and admin

A user with more than one company sees a switcher. The switch requires a membership. An unknown company id returns 403 and leaves the current company in place. The existing session cleanup still clears the cart keys.

Platform admin (role_id 1 or 2) can see company, owner, status, created date, modules, and trial state on the subscriptions screen, and can suspend or reactivate a CUSTOMER company. Those actions are written to `cloud_subscription_events`. The INTERNAL company cannot be suspended from that screen. A suspended company is read-only (`company_suspended`) even if a trial is still inside 24 hours. Records are not deleted. There is no delete-company button.

## Tests

`tests/Feature/CloudOnboardingTest.php`: 7 tests, 109 assertions.

Also re-run and passing:

- CloudPortalTest: 3 tests, 42 assertions
- CloudTenantIsolationTest: 9 tests, 38 assertions
- CloudSubscriptionEnforcementTest: 10 tests, 111 assertions
- CloudPlatformFoundationTest: 7 tests, 99 assertions
- CloudInternalTenantTest: 3 tests, 36 assertions

Covered: all three modules, messaging only, sales only, rentals only, zero modules rejected, duplicate POST, existing email, reserved slugs, INTERNAL type ignored, company switch, public page does not switch company, trial expiry becomes read-only under `Carbon::setTestNow`, suspension is separate from expiry, products, customers, sales, quotations, payments, and bookings stay inside the company that owns them, MAI rental search returns that company's product and does not return Beyond's when asked, and `cloud:audit-ownership` exits 0. The closed-signup test in the isolation suite still creates nothing while the flag is false.

## Abuse controls and remaining limits

Throttle, the one-time token, one introductory trial per company and module, and the phone-and-module claim are the controls. A person who can verify many different phone numbers can still open more than one trial. This phase does not add identity surveillance. Platform admin can see and suspend those companies.

Email is a best-effort welcome after the company is saved, using Laravel mail, not the customer's Messaging plan. `User` does not implement `MustVerifyEmail`, so existing staff are not locked out of the ERP. A mail failure does not delete the company. Phone verification is not required. SMS was not added.

Tenant data export is a later requirement. It was not built here. Company deletion was not built. Sample rows are not offered as a writer; the workspace starts empty.

## Payment

Registration does not take payment. Portal Pay buttons stay hidden while `cloud.payments_live` is false. After a trial ends, the home page uses the configured sentence that the information remains available in read-only mode and that subscription payment will be available shortly. Nothing marks a subscription paid because a visitor says they paid.

PHASE 1F-PROD was not requested. Public signup was not opened.
