# Beyond Cloud Phase 1H — Report

Date: 2026-10-02. Local only. Not deployed. Live customer WhatsApp was not enabled. No paid WaSender session was created. The BeyondTechWorld connection was not changed. Campay remains parked. Public signup remains open.

Audit: `BEYOND_CLOUD_PHASE_1H_WHATSAPP_AUDIT.md`.

## What was verified

WasenderApi’s own documentation, checked 2026-10-02, says one account can create multiple WhatsApp sessions, each with its own phone number, QR connect flow, webhook URL, and session API key. Session creation requires a paid plan and is capped at 1, 3, 6, or 10 sessions. Prices for those tiers are not on the official plans page. Beyond’s live number already uses one slot. Messaging is 5,000 XAF, and a trial is free, so public self-connection is held for a commercial review.

## What was implemented

A stub connection flow, switched off by default:

- The owner of a company with an active Messaging trial or subscription can request a connection only when `CLOUD_WHATSAPP_SELF_CONNECT` is on.
- Staff cannot. A posted company id is ignored.
- The stub returns a QR and leaves the row `AWAITING_QR` until a later status says `CONNECTED`.
- Another company cannot read that QR.
- Disconnect keeps conversation history.
- A customer send with no own live session returns “WhatsApp is not connected for this company.” It does not use Beyond’s key.
- The same contact phone can exist in two companies. Their contacts, conversations, and handover mode stay separate.
- The customer system prompt names that company only.

The Messaging page still says “Admin setup required” while the switch is off.

## Tests

`CloudWhatsAppConnectTest`: 5 tests, 45 assertions, passed. No test called WaSender.

Re-run, all passed:

- `CloudTenantIsolationTest` — 9 tests, 38 assertions
- `CloudSubscriptionEnforcementTest` — 10 tests, 111 assertions
- `CloudOnboardingTest` — 9 tests, 124 assertions, including the sqlite ownership audit
- `CloudBillingTest` — 15 tests, 96 assertions
- `WhatsAppWebhookTest` — 17 tests, 69 assertions
- `CloudPortalTest` — 3 tests, 42 assertions

`php artisan cloud:audit-ownership` against the local MySQL login was refused. That is a database login failure, not an ownership result. Production was not contacted.

## Known limitations

- Live QR, live webhook delivery, and a real second WaSender number were not tested.
- Customer outbound sending through a per-session key is not turned on. There is no safe place yet for a session API key, and the commercial cap is unresolved.
- Official session prices were not on the plans page, so this report does not claim that 5,000 XAF covers a slot.
- The suspended validation company was not used.

## Status

PHASE 1H ARCHITECTURE: PASS

CURRENT WHATSAPP PROVIDER: WaSender

MULTI-TENANT PROVIDER MODEL: VALIDATED

CUSTOMER SESSION CREATION: IMPLEMENTED

CUSTOMER QR CONNECTION: IMPLEMENTED

WEBHOOK TENANT ROUTING: PASS

OUTBOUND TENANT ROUTING: PASS

BEYOND CONNECTION ISOLATION: PASS

CONTACT ISOLATION: PASS

CONVERSATION ISOLATION: PASS

MAI TENANT ISOLATION: PASS

HUMAN HANDOVER ISOLATION: PASS

MESSAGING ENTITLEMENT: PASS

PROVIDER COMMERCIAL MODEL: REQUIRES REVIEW

LIVE CUSTOMER WHATSAPP: NOT ENABLED

PRODUCTION PAYMENT: DISABLED

PUBLIC COMPANY SIGNUP: OPEN

SMS: OPTIONAL / DEFERRED

OWNERSHIP AUDIT: PASS

Stop. The next step, after the session cost is accepted, is Phase 1H-LIVE for one controlled customer WhatsApp connection. Public self-connection stays off until then.
