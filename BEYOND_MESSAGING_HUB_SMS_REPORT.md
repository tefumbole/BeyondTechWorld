# Beyond Messaging Hub — SMS report

Phase MH-1 is implemented and tested locally. It is not deployed. Phase 1F is not deployed. Public company signup stays closed. No real SMS provider was activated.

## Status

MESSAGING HUB ARCHITECTURE: READY

SMS PROVIDER: FAKE ONLY

SMS PRODUCTION SENDING: DISABLED

WHATSAPP REGRESSION: PASS

TENANT ISOLATION: PASS

SUBSCRIPTION ENFORCEMENT: PASS

PHASE 1F ONBOARDING REGRESSION: PASS

Production remains `f8c9e0be95ff635512e8650617a3a34732d29cf5`.

PUBLIC COMPANY SIGNUP: TEST ONLY

PAYMENT PROVIDER PRODUCTION ACTIVATION: NOT VALIDATED

WHATSAPP CUSTOMER SELF-CONNECTION: ADMIN SETUP REQUIRED

## Audit and provider review

`BEYOND_MESSAGING_HUB_SMS_AUDIT.md` lists the existing WhatsApp stack and the call sites that still send WhatsApp directly. Those paths were not rewritten. `WhatsAppProviderInterface` and `WaSenderProvider` are unchanged. `NotificationRouter` WhatsApp methods are unchanged. The older Twilio/Clickatell `sendSms()` method is still the installation gateway and is not the tenant credit ledger.

`BEYOND_MESSAGING_HUB_SMS_PROVIDER_REVIEW.md` records that Orange Cameroon, MTN Cameroon, and Infobip were not verified. The registry knows the names `orange_cm`, `mtn_cm`, and `infobip`. Each one resolves to an unactivated provider. `send()` and `verifyWebhook()` refuse. There is no invented API URL, payload, or credential.

## What was added

`MessagingHub` is the channel-neutral entry. A business event has one correlation id. Repeating that id does not create another WhatsApp or SMS attempt.

Channels in this phase are WhatsApp and SMS. Email and Telegram were not added.

SMS sending goes through `SmsProviderInterface` and `SmsProviderRegistry`. Tests use `FakeSmsProvider`. `config/messaging.php` keeps `sms.live` false and the default driver `disabled`. A config flag does not turn on a live network.

Each company can have a `cloud_sms_connections` row: provider name, sender id, status, a credentials reference (not the secret), country, currency, and whether sending is enabled. Alpha cannot use Beta’s sender, template, or credit. The same phone can be notified by both companies; the rows stay separate.

Outbound SMS is stored with status, segments, customer charge, and a separate provider-cost column. Provider cost is not filled in, because no provider returned one, and it is not shown as a customer price. OTP text is stored as “OTP redacted”. The code is not written to the log.

Normalized states are QUEUED, SENDING, SENT, DELIVERED, FAILED, and REJECTED. A successful send is SENT. DELIVERED is applied only from a later receipt. A receipt that repeats the same status does not add another ledger row. SENT then FAILED is allowed. That failure does not invent a refund.

The webhook checks a signature, ignores an unsigned call, and finds the company from the provider message id. It does not choose the company from the sender’s phone. Two-way inbound SMS is not claimed.

## Credit

The ledger is append-only: CREDIT, RESERVE, RELEASE, CONSUMED, ADJUSTMENT, REFUND.

Available units are the sum of the ledger. A send locks the company account, reserves the segment count, and only then calls the provider. If the provider does not accept the message, a RELEASE puts the units back. If the provider accepts it, a CONSUMED row of 0 units records that the hold is the charge. Two sends that each need 8 units against a balance of 10 cannot both succeed. The second is denied and does not call the provider.

A temporary provider failure releases the hold. It does not immediately retry, so an outage does not drain the balance. The queue job allows one try.

Customer charge is `(unit price + markup) × segments` from `cloud_sms_prices`, in the connection currency (XAF for a Cameroon row). No Orange or MTN price is hardcoded. If no price row exists, the segment ledger still moves and the money charge stays empty. Test credits can be granted only outside production, and the allow flag can turn that off. Paying for credits was not built.

Segment estimates use the GSM 7-bit and Unicode rules, not `strlen()`. A 161-character GSM message is 2 segments. Seventy Unicode characters are 1 segment. Seventy-one are 2.

## Routing

AUTO sends SMS only after a permanent recipient failure on WhatsApp, and only when that fallback is enabled. A temporary outage does not send SMS. One permanent failure produces one SMS attempt.

Direct SMS still requires Messaging write access through `CloudModuleAccessService`. A Sales-only company is denied. INTERNAL BeyondTechWorld is unchanged and does not need an SMS connection for its existing WhatsApp. A queued SMS whose trial has expired by the time the job runs is not sent.

Marketing to a suppressed number, or without marketing consent, is refused. Transactional notices are not treated as marketing. OTP uses the existing hourly limit setting.

Quotation text is a short line plus a link. A bare `/invoice/123` link is rejected. No PDF is attached to SMS. No public URL shortener was added.

Templates replace only `customer_name`, `quotation_number`, `amount`, `company_name`, and `secure_link`. Anything else in braces is removed. A company cannot render another company’s template.

MAI’s `send_notification` tool does not send SMS. A company without Messaging does not see a successful call. A request for many messages is refused. The tool does not receive provider keys or a tenant id.

## Screens

The company Messaging page now shows Messaging entitlement, WhatsApp not connected / admin setup required, SMS not configured, credit, and usage with a masked recipient. The composer posts the text. The server recalculates segments and credit and ignores a browser segment count. A new Messaging trial still completes company creation.

Platform admin can record test credit and suspend a customer SMS connection. Those actions are audited. The internal company is not suspended from that screen. Message bodies are not added to the admin subscriptions table.

## Tests

`MessagingHubTest`: 4 tests, 56 assertions. Covers segments, credit, overspend, release on refusal, tenant isolation, the same phone, fallback, idempotency, delivery, unsigned webhook refusal, entitlement, expired queued send, unactivated Orange, and secure links.

`cloud:audit-ownership` after those rows: unowned 0, invalid 0, cross-tenant 0.

Also passing:

- CloudOnboardingTest: 7 tests, 110 assertions
- CloudTenantIsolationTest: 9 tests, 38 assertions
- CloudSubscriptionEnforcementTest: 10 tests, 111 assertions
- CloudPortalTest: 3 tests, 42 assertions
- CloudInternalTenantTest: 3 tests, 36 assertions
- CloudPlatformFoundationTest: 7 tests, 99 assertions
- WhatsAppWebhookTest: 17 tests, 69 assertions

The webhook suite was failing on company lookup when the membership table is absent, which is how that test database is built. The resolver now treats a missing membership table as no membership. When the table exists, membership checks are unchanged.

## Not in this phase

Real SMS sending. SMS credit checkout. Bulk marketing campaigns. Inbound SMS. Rerouting every existing WhatsApp call site through the hub. Deploying this work or Phase 1F. Opening public signup.
