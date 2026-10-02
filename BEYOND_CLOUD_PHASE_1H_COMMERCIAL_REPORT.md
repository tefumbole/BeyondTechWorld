# Beyond Cloud Phase 1H-C — Commercial capacity report

Date: 2026-10-02. Local only. Not deployed. No live customer WaSender session was created. Customer QR stays off the public Messaging page. `cloud_plans` was not changed. Campay was not touched. Public signup was not closed.

## Provider model already verified

Wasender documentation reviewed on 2026-10-02, and kept from the Phase 1H audit:

- One WaSender account can hold multiple WhatsApp sessions.
- One session is one WhatsApp phone number.
- Each session has its own QR connection, webhook, and session key.
- Published plans cap sessions at 1, 3, 6, or 10.
- The official plans page does not publish a price that can be compared with Messaging.
- BeyondTechWorld already uses one live session. That session was not used for a customer QR, customer send, customer webhook, customer MAI, or a capacity test.

## Actual Beyond account

Local `laravel-app/.env` and `.env.local` are absent. No plan name, invoice, renewal date, or session list could be read without opening production credentials. Production was not contacted.

| Item | Result |
| --- | --- |
| Current plan | UNKNOWN |
| Maximum sessions | UNKNOWN |
| Allocated sessions | Not re-counted this phase |
| Active sessions | Not re-counted this phase |
| Available slots | UNKNOWN |
| Renewal / billing period | NOT AVAILABLE |
| Amount Beyond pays | NOT AVAILABLE |
| BeyondTechWorld live session | 1, from the connection already observed in production (connection id 1, tenant BeyondTechWorld). This phase did not recount it. |

Session keys, API keys, and credentials are not in this report.

## Cost per available session

These figures are the ones that would enter a margin calculation. They are not guessed.

| Input | Value |
| --- | --- |
| Total provider subscription cost | NOT AVAILABLE |
| Maximum included sessions | UNKNOWN |
| Beyond-owned sessions | 1 |
| Remaining customer capacity | UNKNOWN |

An unknown session limit is fail-closed in the application: customer slots offered are 0. The app does not assume 10, and it does not divide an unknown invoice by a session cap.

These stay separate from the provider subscription and were not priced in this phase:

- AI / OpenAI usage
- server cost
- support
- maintenance
- message processing
- a later increase in the provider price

Messaging remains the catalog price: **5,000 XAF** per month (`cloud_plans.code = MESSAGING_MONTHLY`). The database row is still authoritative. It was not edited. Production was not re-queried this phase. The last production display, from Phase 1F, was 5,000 XAF.

No comparison of 5,000 XAF against a WaSender invoice is possible. This report does not call that price profitable.

## Messaging and a dedicated WhatsApp connection

A Messaging subscription is the software module. A dedicated WhatsApp connection is a provider session on Beyond’s WaSender account. One does not include the other.

`canUseMessaging()`, `canProvisionWhatsApp()`, and `canSendWhatsApp()` are separate. A 24-hour Messaging trial can use the module. It does not reserve a WaSender session. Customer sending stays off.

## Commercial models

**Model A — included.** Messaging would pay for one dedicated session. This is appropriate only after the provider invoice, plus the operating costs above, still leaves a margin inside 5,000 XAF. That calculation cannot be made now.

**Model B — WhatsApp add-on.** Messaging stays the base subscription. A dedicated connection is a separate monthly fee. No add-on price is assigned until the invoice is known.

**Model C — higher Messaging plan.** A later catalog could distinguish Messaging, Messaging + WhatsApp, and Messaging + WhatsApp + MAI. No prices were added to `cloud_plans`.

**Model D — customer provider account.** The customer would supply their own supported account. That would move the session cost off Beyond, and it would add support and credential-handling risk. It is not implemented. The current integration creates sessions on Beyond’s account. A customer session key is not stored, and customer sends do not use Beyond’s key.

## Recommendation

Do not include a Beyond-funded WhatsApp session in the 5,000 XAF Messaging plan.

The decision is not “WaSender is too expensive.” The invoice is missing, so a funded slot cannot be shown to fit. Until that invoice exists:

- Dedicated WhatsApp included: **NO**
- WhatsApp add-on required: **UNDECIDED**
- Provisioning policy: **MANUAL_APPROVAL**
- Customer provisioning: **off**
- Trial provisioning: **off**

When the invoice is available, compare:

provider subscription cost + an infrastructure share + an AI allowance + a support allowance + the margin Beyond wants

against the monthly amount the customer pays. If that total does not fit 5,000 XAF, keep Messaging as it is and choose an add-on (Model B) or a higher Messaging tier (Model C). Do not turn Messaging off. Model A is only for the case where the full stack fits. Model D waits until a customer-owned account can be attached without placing that customer on Beyond’s session or Beyond’s key.

## Capacity architecture

`WhatsAppCapacityService` answers the slot question before any session row is handed to the provider stub.

- Plan capacity comes from `WASENDER_SESSION_LIMIT`. Empty means unknown, and unknown offers 0 customer slots. Changing plan does not require a code change.
- `WASENDER_RESERVED_SESSIONS` defaults to 1. The reserve is at least the count of BeyondTechWorld holding sessions, so a customer cannot take the live company slot.
- Holding customer rows are `PROVISIONING`, `AWAITING_QR`, `CONNECTING`, `CONNECTED`, and customer `ACTIVE`. `DISCONNECTED` and `ERROR` do not hold a slot.
- Before the stub is called, one database transaction locks `cloud_whatsapp_capacity_guard` and inserts a `RESERVED` row. If the stub returns no session id, the connection becomes `ERROR` and the reservation becomes `RELEASED`. A successful stub call marks the reservation `CONSUMED` and the connection `AWAITING_QR`.
- If no slot remains, the request stops with “WhatsApp connection capacity currently unavailable.” It does not fall back to Beyond’s connection.
- Two reservations of the last slot are refused inside that locked transaction. PHPUnit runs in one process, so the proof is that second reserve, not two operating-system processes.

Platform Admin (`/admin/subscriptions/whatsapp`) sees plan capacity, Beyond sessions, customer sessions, provisioning, reserved, and available. Unknown capacity is shown as Unknown. The inventory lists tenant, company, status, provider, a masked phone, the session identifier, created time, and last health. It does not render a session key, an API secret, or `credentials_reference`. A customer receives 403.

## Provisioning policy

Configuration, all conservative by default:

| Setting | Default |
| --- | --- |
| `customer_whatsapp_provisioning_enabled` (`CLOUD_WHATSAPP_PROVISIONING`) | false |
| `provisioning_policy` (`CLOUD_WHATSAPP_POLICY`) | `MANUAL_APPROVAL` |
| `session_limit` (`WASENDER_SESSION_LIMIT`) | empty / unknown |
| `reserved_sessions` (`WASENDER_RESERVED_SESSIONS`) | 1 |
| `trial_provisioning_allowed` (`CLOUD_WHATSAPP_TRIAL_PROVISIONING`) | false |
| `customer_send_enabled` (`CLOUD_WHATSAPP_CUSTOMER_SEND`) | false |
| `whatsapp_self_connect` | false |

Supported policy names: `MANUAL_APPROVAL`, `PAID_ADDON_REQUIRED`, `INCLUDED_IN_PLAN`, `CUSTOMER_PROVIDER_ACCOUNT`. Controllers do not hard-code one model. Only `INCLUDED_IN_PLAN`, with provisioning switched on, a numeric limit, a paid Messaging subscription, and a free customer slot, can reserve a stub session. `MANUAL_APPROVAL`, `PAID_ADDON_REQUIRED`, and `CUSTOMER_PROVIDER_ACCOUNT` do not provision. There is no approval record, no add-on product, and no customer credential store yet.

Public registration does not call session creation. A Messaging trial does not either.

Until a later approval, Messaging → WhatsApp still says **Admin setup required**. The QR route answers 404 while provisioning is off.

## Tests

Stub provider only. No test called WaSender.

`CloudWhatsAppCapacityTest`: 12 tests, 59 assertions, passed.

- capacity available after the Beyond reserve
- unknown limit and an exhausted limit refuse another session
- reserved sessions are not given to a customer
- the final slot cannot be reserved twice
- a failed provider call releases the reservation
- a Messaging trial does not create a session, and module access stays separate from connection access
- a customer session does not replace or send through the Beyond session
- disabled provisioning blocks the QR page
- raising or clearing `WASENDER_SESSION_LIMIT` changes capacity without a code edit
- Platform Admin inventory hides secrets; a customer is denied
- a customer `CONNECTED` row does not change the single `ACTIVE` queue tenant

`CloudWhatsAppConnectTest`: 5 tests, 45 assertions, passed. The QR cases opt into a paid subscription and `INCLUDED_IN_PLAN`. The default page still hides Connect.

Re-run, all passed:

- `CloudBillingTest` — 15 tests, 96 assertions
- `CloudOnboardingTest` — 9 tests, 124 assertions, including `cloud:audit-ownership` on sqlite
- `CloudSubscriptionEnforcementTest` — 10 tests, 111 assertions (Sales, Rentals, and MAI entitlement)
- `CloudTenantIsolationTest` — 9 tests, 38 assertions (tenant isolation and MAI)
- `WhatsAppWebhookTest` — 17 tests, 69 assertions
- `CloudPortalTest` — 3 tests, 42 assertions

`php artisan cloud:audit-ownership` was not run against local MySQL. There is no local `.env`. The earlier local MySQL attempt failed on the `forge` login. That is a login failure, not an ownership count. Production was not contacted. The sqlite onboarding audit command completed.

## Known limitations

- Plan, invoice, renewal, and live session counts were not read from WaSender.
- No margin math was produced, because the provider cost is missing.
- Customer QR, live webhook delivery, and a second real number were not tested.
- Customer outbound sending stays off. A per-session key is not stored.
- The concurrency proof is a locked transaction in one PHP process.
- The suspended validation company was not used.

## Status

WASENDER PLAN: UNKNOWN

SESSION CAPACITY: UNKNOWN

BEYOND SESSIONS: 1

AVAILABLE CUSTOMER CAPACITY: UNKNOWN

WASENDER COST: NOT AVAILABLE

MESSAGING PLAN: 5,000 XAF

DEDICATED WHATSAPP INCLUDED: NO

WHATSAPP ADD-ON REQUIRED: UNDECIDED

TRIAL AUTO-PROVISIONING: DISABLED

CUSTOMER SELF-CONNECTION: DISABLED

CAPACITY SERVICE: IMPLEMENTED

CONCURRENCY PROTECTION: PASS

BEYOND CONNECTION ISOLATION: PASS

PUBLIC COMPANY SIGNUP: OPEN

PRODUCTION PAYMENT: DISABLED

SMS: OPTIONAL / DEFERRED

OWNERSHIP AUDIT: PASS

Stop here. Phase 1H-LIVE waits until the provider invoice is known and a packaging decision is made.
