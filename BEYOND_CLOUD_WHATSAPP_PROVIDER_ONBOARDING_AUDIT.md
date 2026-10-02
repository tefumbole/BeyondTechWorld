# Beyond Cloud — WhatsApp provider onboarding audit

Audit date: 2026-10-02. No provider account was changed. No customer session was created.

## Current provider

BeyondTechWorld sends through WasenderAPI. Configuration is one API key and one session id:

- `config('services.whatsapp.wasender_api_key')` from `WASENDER_API_KEY`
- `config('services.whatsapp.wasender_session_id')` from `WASENDER_SESSION_ID`

The secret is not stored on `cloud_whatsapp_connections`. That table stores `credentials_reference` as the config key name. Production has one ACTIVE connection, and it belongs to the INTERNAL BeyondTechWorld tenant. Phase 1D resolves inbound webhooks from that connection, not from the sender’s phone.

`WhatsAppProviderInterface` is the send contract (`sendText` and the related Wasender adapter). SaaS onboarding must keep that interface. A later official WhatsApp Business provider can sit behind it. Onboarding must not call Wasender session APIs directly from the registration form.

## What this account is known to support

The application code assumes one installation session:

- `CloudWhatsAppConnectionResolver::ensureInternalConnection()` creates a row only when the table is empty, and only for the INTERNAL slug `beyondtechworld`.
- If more than one ACTIVE connection exists and the webhook has no session id, resolution fails closed.
- There is no code that creates a Wasender subaccount, a second session, or a QR code for a customer-owned number.

Public WasenderAPI product pages describe extra sessions as a paid add-on on the provider account. This repository does not contain proof that the live Beyond subscription includes extra sessions, subaccounts, or a customer QR flow. That was not tested against the live provider.

## Commercial and technical limits

| Question | Finding |
| --- | --- |
| Multi-session on the current key | Not implemented. Not confirmed on the live subscription. |
| Subaccount per customer | Not implemented. |
| Customer connects their own number | No QR or embedded-signup flow in this codebase. |
| Webhook routing | One webhook URL. The session id on the payload picks the connection. A second company needs its own connection row and its own provider session id. |
| Credentials | One platform key. Showing it, or the Beyond session id, to a customer would share Beyond’s inbox. |

## Decision for Phase 1F

Customer self-service WhatsApp connection is **ADMIN SETUP REQUIRED / NOT READY**.

A Messaging trial may still start. The company workspace shows:

- Messaging trial active
- WhatsApp: Not connected
- A platform administrator must attach a connection later

The signup flow does not create a `cloud_whatsapp_connections` row and does not read the Beyond API key or session id into the customer page.
