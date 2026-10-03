# Beyond Messaging Hub — Infobip live validation

Phase: MH-2L. Date: 2026-10-02.

Live validation stopped at the precondition gate. No SMS was sent. No Infobip HTTP call was made. Messaging Hub and Phase 1F were not deployed. Public company signup was not opened. Business SMS was not enabled.

Production application HEAD remains `f8c9e0be95ff635512e8650617a3a34732d29cf5`.

## Gate

Checked in the local application configuration on 2026-10-02. Values were not printed.

| Requirement | Result |
| --- | --- |
| Infobip account active | Not available. No account is connected to this application. |
| Cameroon destination sending enabled | Not checked. There is no account to inspect. |
| SaaS / multi-customer use permitted on the account | Not confirmed. CPaaS X documentation describes one main account and customer entities. That documentation is not a confirmation from an Infobip account. |
| API key installed securely | Absent. `INFOBIP_API_KEY` is not set. |
| API base URL from the Infobip account | Absent. `INFOBIP_BASE_URL` is not set. |
| Sender configured or approved | Absent. `INFOBIP_SENDER` is not set. |
| Authorized Orange Cameroon handset | Not provided. |
| Authorized MTN Cameroon handset | Not provided. |
| Validation ceiling | Present. 20 segments. It was not raised. |
| Automated tests | Passing. See regressions below. |
| Driver | `disabled`. `MESSAGING_SMS_DRIVER` was not switched to `infobip`. |

The missing account, credentials, sender, and handsets are mandatory. The phase stops here. Credentials were not invented, and the driver was not turned on in order to attempt a send.

## Infobip account

INFOBIP ACCOUNT: NOT ACTIVE

No portal session was opened. No connectivity check was run. Infobip documents a non-send account API, but calling it requires the base URL and API key, and both are absent.

## Cameroon enablement

Not checked on an account. The coverage page lists Cameroon as a destination and requires sender registration. That page does not name Orange or MTN. No live destination was tested.

## Sender

SENDER: NOT CONFIGURED

There is no configured sender, no approval record, and no handset display to compare.

## SaaS / multi-customer suitability

INFOBIP SAAS/MULTI-CUSTOMER USE: NOT CONFIRMED

The published CPaaS X pages describe entities as customers on one main account. They do not explicitly authorize marking up SMS and selling credits. This phase did not obtain an account-level confirmation. No customer tenant was created. No commercial SMS price was charged.

## Live messages

Segments consumed during validation: 0.

| Test | Result |
| --- | --- |
| Orange Cameroon | NOT VALIDATED. Not sent. |
| MTN Cameroon | NOT VALIDATED. Not sent. |
| Cross-network | Not established. |
| Delivery reports | NOT VALIDATED. No live report was received. A replay was not performed. |
| Sender displayed on a handset | Not observed. |
| Unicode | NOT VALIDATED. Not sent. |
| Multi-segment | NOT VALIDATED. Not sent. |
| Idempotency on a real send | NOT VALIDATED. Not sent. The stubbed adapter test still shows one provider call for one correlation id. |
| Ledger on a real send | NOT VALIDATED. No reservation or consumption was created for a live message. The existing ledger path was not bypassed. |
| Provider cost, network, billing units | NOT AVAILABLE / NOT VALIDATED. |
| API acceptance to handset time | Not observed. |
| API acceptance to delivery-report time | Not observed. |
| Infobip portal reconciliation | Not performed. There are no live message ids to compare. |

WHATSAPP → SMS FALLBACK: NOT EXERCISED

Failure cases were not created by sending to invalid numbers or by changing credentials. `FakeSmsProvider` remains the deterministic path for outage, permanent recipient failure, timeout, and rejection.

## Security review

- The API key is absent from application configuration.
- A search of tracked project files found no assigned `INFOBIP_API_KEY`.
- The key is not in this report.
- No browser response was generated for a live send.
- MAI was not given the key, and real SMS through MAI stays disabled.
- Application logs were not written by an Infobip request, because no request was made.

## Regressions

Run locally on 2026-10-02. PHPUnit did not call Infobip. `FakeSmsProvider` stayed the default except inside the stubbed Infobip file.

| Suite | Result |
| --- | --- |
| InfobipSmsProviderTest | 3 tests, 24 assertions, pass |
| MessagingHubTest | 4 tests, 56 assertions, pass |
| CloudOnboardingTest | 7 tests, 110 assertions, pass |
| CloudTenantIsolationTest | 9 tests, 38 assertions, pass |
| CloudSubscriptionEnforcementTest | 10 tests, 111 assertions, pass |
| CloudPortalTest | 3 tests, 42 assertions, pass |
| CloudInternalTenantTest | 3 tests, 36 assertions, pass |
| CloudPlatformFoundationTest | 7 tests, 99 assertions, pass |
| WhatsAppWebhookTest | 17 tests, 69 assertions, pass |

## Ownership audit

`MessagingHubTest` runs `php artisan cloud:audit-ownership` on the test database. That assertion passed: unowned 0, invalid 0, cross-tenant 0.

The same command against the local application database did not run. That database rejected the login. Production was not contacted.

## Known limitations

Live Orange delivery, MTN delivery, receipts, sender display, Unicode, multi-segment behavior, idempotency, and ledger consumption are all unproven. The 20-segment ceiling is still in force and unused.

MH-2L does not pass. MH-PROD should not start from this result.

## Explicit status

```
INFOBIP ACCOUNT:
NOT ACTIVE

INFOBIP SAAS/MULTI-CUSTOMER USE:
NOT CONFIRMED

SENDER:
NOT CONFIGURED

ORANGE CAMEROON:
NOT VALIDATED

MTN CAMEROON:
NOT VALIDATED

DELIVERY RECEIPTS:
NOT VALIDATED

UNICODE:
NOT VALIDATED

MULTI-SEGMENT:
NOT VALIDATED

IDEMPOTENCY:
NOT VALIDATED

LEDGER:
NOT VALIDATED

WHATSAPP → SMS FALLBACK:
NOT EXERCISED

SMS PRODUCTION BUSINESS NOTIFICATIONS:
DISABLED

PHASE 1F:
NOT DEPLOYED

PUBLIC COMPANY SIGNUP:
CLOSED
```
