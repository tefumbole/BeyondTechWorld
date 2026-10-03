# Beyond Messaging Hub — SMS provider review

Review date: 2026-10-02. Official pages were read on that date. No provider console was opened. No API key was stored. No message was sent to a mobile network.

SELECTED INITIAL PROVIDER: Infobip

## Why Infobip

The intended first connection is one BeyondTechWorld provider account, used by more than one CloudTenant, with Beyond recording each tenant’s SMS usage. Of the three providers checked, only Infobip publishes that account shape.

Infobip CPaaS X, read at `https://www.infobip.com/docs/cpaas-x` and `https://www.infobip.com/docs/cpaas-x/get-started`, says a main account can manage communications for multiple customers. Applications are environments. Entities are customers, brands, or business units. Traffic, resources, and reporting are isolated per entity. A send includes `options.platform.applicationId` and `entityId`. CPaaS X is not available on subaccounts. A sender is still required unless a sending strategy is configured.

Those pages do not say that Beyond may mark up Infobip’s price and sell SMS credits to tenants. They also do not forbid it. Public SMS-credit purchasing is not part of this phase. Before any customer is charged a commercial SMS price, that commercial point needs a direct confirmation from Infobip. The adapter does not open a credit shop.

Orange’s SMS getting-started guide describes prepaid bundles used to message “your customers.” A sample contract JSON contains `type` values `SELFSERVICE` and `BROKER`. That sample is not a Cameroon reseller right. Orange Network API terms that forbid sublicensing apply to Network APIs such as KYC and number verification, not to this SMS product. Those terms were not treated as permission, and they were not treated as an SMS ban.

MTN’s production SMS v3 product page, `https://developers.mtn.com/products/sms-v3-api`, listed Benin and Congo (the) on 2026-10-02. The staging catalog listed Cameroon for SMS V2 and SMS V3. Staging was not treated as production. The MADAPI SMS interface is an MTN send API. It does not document delivery to Orange Cameroon.

## Infobip

Official sources checked on 2026-10-02:

- `https://www.infobip.com/docs/sms/sms-coverage-and-connectivity` (Cameroon section)
- `https://www.infobip.com/docs/api/channels/sms` send reference already captured for `POST {baseUrl}/sms/3/messages`
- `https://www.infobip.com/docs/api/channels/sms/logs-and-status-reports/receive-outbound-sms-message-report`
- `https://www.infobip.com/docs/cpaas-x`
- `https://www.infobip.com/docs/cpaas-x/get-started`
- `https://www.infobip.com/docs/cpaas-x/applications-and-entities`

| Topic | What the pages say |
| --- | --- |
| Cameroon availability | Country CM, dialling code 237, MCC 624. Number portability: No. Local versus international classification: Yes. |
| Destination networks | The Cameroon section does not name Orange, MTN, Camtel, or Nexttel. Cross-network delivery is not established by this page. |
| API authentication | Outbound send uses `Authorization: App {apiKey}`. |
| Outbound SMS | `POST {baseUrl}/sms/3/messages`. Body includes `sender`, `destinations[].to`, `content.text`. A client `messageId` may be sent; otherwise Infobip generates one and returns it. |
| Sender ID | Alphanumeric, short code, and long numeric senders are supported for Cameroon, local and international. Registration is required for both. |
| Sender approval | Local registration asks for company address, name, website, English content sample, country, message example, service or brand name, and use case. Documents: Cameroon Sender Registration form and proof of local presence. International registration also asks for contact details, monthly volume, MTN OpCos, SMPP/SS7 account id, and traffic type. Duration on the page: +20 days, local and international. |
| Delivery receipts | Delivery webhook and report polling exist. A send response of `PENDING` / `PENDING_ACCEPTED` means the API accepted the message. It does not mean the handset received it. |
| Webhook | The callback body is JSON `results[]` with `messageId`, `smsCount`, `price.pricePerMessage`, `price.currency`, `status.groupName`, `status.name`, and `error.permanent`. The server must return HTTP 200. The page’s Authentication section lists APIKeyHeader, Basic, IBSSOTokenHeader, and OAuth2. It does not describe an HMAC over the body. The send tutorial’s example callback does not show Infobip attaching those headers. |
| Two-way SMS | Cameroon row: 2-way SMS supported: No. |
| Unicode | The send API accepts a text body. The coverage page does not give a Cameroon-specific Unicode rule. `messageCount` is returned when `includeSmsCountInResponse` is true. |
| Provider message IDs | `messageId` is the correlation key. |
| Idempotency | A caller-supplied `messageId` is the documented reference. A second logical notification in this application must not create a second provider request. |
| Rate limits | Not stated on the Cameroon coverage page. Not invented here. |
| Pricing | A delivery report may include `price.pricePerMessage` and a currency. The example currency is EUR. Cameroon XAF prices were not published on these pages. |
| Account | A main Infobip account, an API key, and enabled SMS. CPaaS X entities represent customers. |
| Prepaid or postpaid | Not stated on the pages read. Not invented here. |
| Subaccounts | CPaaS X is not available on subaccounts. |
| Multi-tenant | Documented through applications and entities on one main account. |
| Production approval | Sender registration is required before a chosen sender can be used. |
| Sandbox | These pages describe the live SMS API. A separate no-charge Cameroon sandbox was not documented here. |

## Orange Cameroon

Official source checked on 2026-10-02: Orange SMS Cameroon / SMS Africa and Middle East getting started on `developer.orange.com`.

| Topic | What the page says |
| --- | --- |
| Cameroon availability | Cameroon is in the country table. The country sender address is `tel:+2370000`. |
| Destination networks | The SMS Cameroon FAQ says there are known delivery problems toward MTN because of MTN SMS rules, and tells the reader to contact the local team. An Orange-only offer exists and is selected with `resource_type_parameter_management=SMS_OCB2` only when that offer is subscribed. |
| Cross-network | Not established. `DeliveryUncertain` is documented as status unknown, including when the message was handed to a network other than Orange. |
| API authentication | OAuth2 client credentials, `POST https://api.orange.com/oauth/v3/token`, then `Authorization: Bearer`. Tokens last about 3600 seconds. HTTP 401 code 42 means expired credentials. |
| Outbound SMS | `POST https://api.orange.com/smsmessaging/v1/outbound/tel%3A%2B2370000/requests`. |
| Sender ID | Default sender is the country address. A custom `senderName` works only after it is whitelisted. Maximum 11 alphanumeric characters or spaces. Special characters are not allowed. An unapproved name returns HTTP 400. |
| Sender approval | The local team whitelists the name. The FAQ gives about 5 working days for Orange and about 15 working days for an MTN sender. Do not use the name before the confirmation email. |
| Delivery receipts | Callback within 24 hours, HTTPS port 443, certificate must not be self-signed, response must be HTTP 200. The callback URL is registered with Orange. Orange then supplies a public IP to whitelist. No HMAC is documented. |
| Status values | `DeliveredToNetwork`, `DeliveryUncertain`, `DeliveryImpossible`, `MessageWaiting`, `DeliveredToTerminal`. `DeliveredToTerminal` means the handset received the message. Orange does not refund `DeliveryImpossible`. |
| Provider message IDs | The 201 response `resourceURL` carries the resource id used later as `callbackData`. |
| Idempotency | Not documented as a client idempotency key on the send call. |
| Rate limits | 5 SMS per second. The contract must be unexpired and have a positive unit balance. |
| Pricing | Prepaid bundles of units. The sample prices on the page are not Cameroon XAF prices. |
| Account | An Orange developer contract and a purchased bundle. |
| Multi-tenant | Not documented as a Cameroon reseller or subaccount model. |
| Sandbox | The getting-started flow is the contracted API, not a separate Cameroon handset sandbox. |

## MTN

Official sources checked on 2026-10-02:

- MADAPI SMS swagger, version 3.0.0, last updated 2026-09-23, production host `https://api.mtn.com/v1`
- `https://developers.mtn.com/products/sms-v3-api`
- `https://staging.developers.mtn.com/products`
- `https://mtn.cm/businesssolutions/mtn-pro-sms/` (marketing page, not the developer contract)

| Topic | What those sources say |
| --- | --- |
| Cameroon availability | Staging lists SMS V2 and SMS V3 in Cameroon. The production SMS v3 page listed Benin and Congo (the). Production Cameroon availability of that developer API was not confirmed. |
| Destination networks | The swagger sends through MTN. It does not document Orange Cameroon delivery. |
| Marketing portal | MTN Pro SMS on mtn.cm describes scheduled SMS, templates, and personalised sender IDs “across all networks.” That page is not the MADAPI contract. |
| API authentication | OAuth2 client credentials. Token URL on the swagger: `https://api.mtn.com/v1/oauth/access_token`. |
| Outbound SMS | `POST /messages/sms/outbound` with `clientCorrelator`, `message`, and `senderAddress`. |
| Delivery | `GET /messages/sms/outbound/{senderAddress}/{requestId}/deliveryStatus`. A subscription path exists for callbacks. |
| Idempotency | `clientCorrelator` is a required client reference. |
| Sender, receipts, Unicode, price, reseller rules | Not confirmed for a Cameroon production developer account. Not invented here. |
| MoMo developer site | Payments, not SMS. |

## Decision record

Infobip is the initial provider because its current documentation is the one that matches a single platform account serving many customer entities, and because its SMS API documents message ids, delivery reports, part counts, and an optional provider price.

Orange is not the initial provider because its own FAQ does not establish MTN delivery, and no Cameroon broker right was documented.

MTN is not the initial provider because the production developer product page did not list Cameroon, and Orange delivery was not documented.

No provider was ranked by an assumed price or an assumed delivery rate.

## Code consequence

`SmsProviderRegistry` still returns `FakeSmsProvider` when `messaging.sms.driver` is `fake`. The default driver remains `disabled`. `orange_cm` and `mtn_cm` stay unactivated. `infobip` returns `InfobipSmsProvider` only when the driver is `infobip`.

`messaging.sms.live` remains false. That flag does not place an API key in the repository and does not send a message by itself.
