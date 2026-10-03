# Beyond Messaging Hub — real SMS provider report

Phase: MH-2. Date: 2026-10-02.

This phase adds one real provider adapter on the MH-1 interface. It does not deploy MH-1, MH-2, or Phase 1F. Public company signup stays closed. Business SMS stays off.

## Selected provider

SELECTED INITIAL PROVIDER: Infobip

The factual basis is in `BEYOND_MESSAGING_HUB_SMS_PROVIDER_REVIEW.md`. Infobip CPaaS X is the documented model of one main account with an entity per customer. Orange does not document a Cameroon reseller right and documents uncertain delivery once a message leaves the Orange network. MTN’s production SMS v3 page did not list Cameroon on the date checked.

## Official documentation

Checked on 2026-10-02:

- Infobip Cameroon coverage: `https://www.infobip.com/docs/sms/sms-coverage-and-connectivity`
- Infobip send API: `POST {baseUrl}/sms/3/messages`
- Infobip delivery report: `https://www.infobip.com/docs/api/channels/sms/logs-and-status-reports/receive-outbound-sms-message-report`
- Infobip CPaaS X: `https://www.infobip.com/docs/cpaas-x` and `https://www.infobip.com/docs/cpaas-x/get-started`
- Orange SMS getting started on `developer.orange.com`
- MTN MADAPI SMS swagger and `https://developers.mtn.com/products/sms-v3-api`

## Cameroon coverage

Infobip lists Cameroon (CM, 237, MCC 624). Sender registration is required. The Cameroon section does not name Orange, MTN, Camtel, or Nexttel. Two-way SMS for Cameroon is documented as not supported.

CAMEROON CROSS-NETWORK VALIDATED: no. No handset test was run.

## Commercial and account requirements

A main Infobip account, an API key, SMS enabled on that account, and a registered sender. CPaaS X entities are the documented way to separate customers. CPaaS X is not available on an Infobip subaccount.

The pages do not explicitly say Beyond may mark up the provider price and sell SMS credits. They do not forbid it either. This phase does not sell credits. Test credit remains an audited internal adjustment, and it is refused when `APP_ENV` is production or `messaging.sms.allow_test_credits` is false. Public SMS purchasing stays off. Payment providers stay not validated.

No Infobip account was opened in this session. These environment names are absent locally: `INFOBIP_BASE_URL`, `INFOBIP_API_KEY`, `INFOBIP_SENDER`, `INFOBIP_ENTITY_ID`, `INFOBIP_APPLICATION_ID`, `INFOBIP_DELIVERY_URL`. No value from any of them is written here.

## Adapter

REAL PROVIDER ADAPTER: IMPLEMENTED

`InfobipSmsProvider` implements `SmsProviderInterface`. The hub, ledger, queue job, tenant context, and `FakeSmsProvider` are unchanged in role.

The adapter is selected only when `messaging.sms.driver` is `infobip`. The default remains `disabled`. Automated tests keep `fake`. PHPUnit does not call Infobip. The HTTP call uses PHP curl with a 5 second connect timeout and a 15 second request timeout. The project’s Guzzle client cannot be constructed here because `Psr\Http\Client\ClientInterface` is not installed, so the adapter does not use that client. Laravel was not upgraded.

The API key is read from config at send time and placed only on the outbound `Authorization` header. It is not written to `cloud_tenant_settings`, Blade, JavaScript, the message row, or this report. The connection stores `credentials_reference` as the env key name `env:INFOBIP_API_KEY`.

A send is refused, with no HTTP call, when the base URL, API key, or sender is empty.

## Authentication

Outbound: `Authorization: App {apiKey}`, as documented for the SMS API.

The request sends the recipient digits, the text, the approved sender, and `messageId` set to the attempt reference. Optional `options.platform.entityId` and `applicationId` are included only when those env values are set. `includeSmsCountInResponse` is true so Infobip can return `messageCount`. A delivery URL is attached only when `INFOBIP_DELIVERY_URL` is set.

## Sender behavior

Cameroon alphanumeric, short code, and long numeric senders require registration. Duration on the coverage page is +20 days. A name typed by a tenant is not activated.

Connection status values already used by the hub are `ACTIVE` plus the operational values `NOT_CONFIGURED`, `PENDING_APPROVAL`, and `REJECTED`. Sending requires `ACTIVE` and `sending_enabled`. The first live sender must be an identity Infobip has already approved on the Beyond account. ALPHA, BETA, and OKUSOMA are not assumed to be approved.

Future customer sender path:

1. The CloudTenant requests a sender name.
2. A Platform Admin reviews the request.
3. The name is submitted to Infobip and stays `PENDING_APPROVAL`.
4. After Infobip confirms it, the connection becomes `ACTIVE`.
5. Only then may that tenant send with that sender.

## Delivery receipts

`POST /cloud/messaging/sms/webhook/infobip` reads `results[]`. Each row is matched by `provider_message_id`. `DELIVERED` is applied only from `status.groupName` `DELIVERED`. A repeated `DELIVERED` callback does not add another ledger row and does not send again.

The official page lists API key, Basic, IBSSO token, and OAuth2 in its Authentication section, and it says the callback endpoint returns HTTP 200. It does not document an HMAC. The send tutorial’s sample callback does not show Infobip sending an Authorization header. This phase does not invent an HMAC check. Until a live callback is observed, receipt authentication is not treated as validated. The route stays available only for the documented JSON shape while the driver is `infobip`.

## Status mapping

| Infobip `groupName` | Internal result |
| --- | --- |
| `PENDING`, `ACCEPTED` on the send response | Accepted. Message status `SENT`. Not `DELIVERED`. |
| `DELIVERED` on a delivery report | `DELIVERED` |
| `UNDELIVERABLE`, `EXPIRED`, `REJECTED` | Not accepted on the send response, or `FAILED` if a later report arrives for a `SENT` message |
| Any other group | Raw status is stored. Normalized status is not changed to a success. |

`error.permanent` is kept on the parsed report. A timeout or a body that is not the documented message object is `provider_timeout` or `provider_rejected` and is not retried by the job (`tries` is 1). An ambiguous timeout is not sent again.

## Credit, segments, and cost

The ledger still reserves the MH-1 segment estimate before the provider call. Acceptance consumes that reservation. Refusal releases it. `DELIVERED` does not debit again.

`cloud_sms_messages.segments` remains the local estimate. `provider_units` stores Infobip `messageCount` or report `smsCount` when the API returns it. They are not forced to be equal.

`provider_cost` stores `price.pricePerMessage` only when the report includes it. The example currency is EUR. It is not converted and it is not shown as the customer charge. If the field is absent, cost stays empty.

No commercial tenant SMS price was added. The validation ceiling is 20 estimated segments for all Infobip traffic while the driver is `infobip`. A send that would pass that ceiling is refused before HTTP. The ceiling is a count, not a secret.

## Live test evidence

PROVIDER ACCOUNT: not opened. It is not sandbox, test, or production, because no account exists in this environment.

No authorized Orange Cameroon number and no authorized MTN Cameroon number were available. No SMS was sent. Customer numbers were not used.

| Check | Result |
| --- | --- |
| Orange Cameroon handset | NOT VALIDATED |
| MTN Cameroon handset | NOT VALIDATED |
| Unicode handset | NOT VALIDATED |
| Long-message handset | NOT VALIDATED |
| Invalid-number live test | NOT RUN. Fake provider still covers deterministic refusal. |
| Delivery receipt from Infobip | NOT VALIDATED. The parser was tested with a fixture, not a live callback. |
| Sender ID on a handset | NOT VALIDATED. No sender is registered. |
| WhatsApp to SMS live fallback | NOT EXERCISED |
| Provider billing units on a real send | NOT VALIDATED |

The stubbed adapter test proved, without network access:

- `PENDING_ACCEPTED` becomes `SENT`, with provider message id `ib-100`, and does not become `DELIVERED` until the report.
- Local segments stay 1 while returned `messageCount` 2 is stored in `provider_units`.
- The same correlation id produces one provider call.
- A second `DELIVERED` report does not add a second `CONSUMED` ledger row.
- An unknown group does not overwrite `DELIVERED`.
- An empty sender and a zero ceiling do not call HTTP.
- Driver `disabled` still returns `provider_not_activated`.

## Queue and security

Real sends still go through `SendSmsAttemptJob`. The job sets `CloudTenantContext` and clears it in `finally`. The controller does not call Infobip directly. Under PHPUnit the queue driver is `sync`, so the stubbed test executed that job inline. A live worker was not observed.

Browser `cloud_tenant_id` is not accepted as authority. MAI `send_notification` still returns `sms_production_disabled`. Bulk SMS, campaigns, and CSV upload were not enabled. OTP, quotations, invoices, rentals, attendance, and internship were not pointed at this adapter.

Health on the connection is `ACTIVE` after an accepted send and `DEGRADED` after a provider timeout or a non-message response. A missing sender does not mark the provider down. Credentials are not shown. The company messaging page shows provider, sender, and health only when that company’s connection is `ACTIVE` and sending is enabled. Otherwise it still says `SMS: Not configured`.

## Regressions

Run locally on 2026-10-02. Fake SMS remained the default for every suite except the stubbed Infobip file.

| Suite | Result |
| --- | --- |
| InfobipSmsProviderTest | 3 tests, 24 assertions, pass. No network. |
| MessagingHubTest | 4 tests, 56 assertions, pass |
| CloudOnboardingTest | 7 tests, 110 assertions, pass |
| CloudTenantIsolationTest | 9 tests, 38 assertions, pass |
| CloudSubscriptionEnforcementTest | 10 tests, 111 assertions, pass |
| CloudPortalTest | 3 tests, 42 assertions, pass |
| CloudInternalTenantTest | 3 tests, 36 assertions, pass |
| CloudPlatformFoundationTest | 7 tests, 99 assertions, pass |
| WhatsAppWebhookTest | 17 tests, 69 assertions, pass |

`MessagingHubTest` runs `php artisan cloud:audit-ownership` and expects `unowned_total=0`, `invalid_total=0`, and `cross_tenant_total=0`. That assertion passed.

## Operational requirements

When an account is opened later:

- Keep the API key only in server environment. Rotate it from the Infobip portal and update the env value. Do not put it in git.
- Turn on portal MFA if the Infobip account offers it.
- Limit portal administrators to named operators.
- Track sender requests through `PENDING_APPROVAL` until Infobip confirms them.
- Watch the 20-segment validation ceiling before any larger limit is chosen.
- On an incident, set the driver back to `disabled`. Queued Infobip sends then fail closed instead of calling the network.

## Known limitations

- No live Cameroon delivery, receipt, sender, or segment proof.
- Infobip’s Cameroon page does not list the destination networks.
- Commercial markup of SMS credits is not an explicit grant in the pages read.
- Inbound webhook authentication was not observed from Infobip. HMAC was not invented.
- Two-way SMS is documented as unsupported for Cameroon.
- `orange_cm` and `mtn_cm` remain unactivated names.

## Explicit status

```
SELECTED SMS PROVIDER:
Infobip

REAL PROVIDER ADAPTER:
IMPLEMENTED

PROVIDER ACCOUNT:
NOT OPENED

ORANGE CAMEROON DELIVERY:
NOT VALIDATED

MTN CAMEROON DELIVERY:
NOT VALIDATED

DELIVERY RECEIPTS:
NOT VALIDATED

SENDER ID:
NOT CONFIGURED

SMS BILLING/SEGMENTS:
NOT VALIDATED

WHATSAPP → SMS LIVE FALLBACK:
NOT EXERCISED

SMS PRODUCTION BUSINESS NOTIFICATIONS:
DISABLED

PUBLIC COMPANY SIGNUP:
CLOSED

PHASE 1F:
NOT DEPLOYED
```

MH-2 is not ready for production deployment preparation. The pass condition still needs an authorized handset, a stored live provider message id, a live ledger consumption, and a live duplicate check. Those were not run because there is no provider account and no authorized test number.

Production application HEAD remains `f8c9e0be95ff635512e8650617a3a34732d29cf5`. Nothing in this phase was deployed.
