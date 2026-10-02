# Beyond Cloud Phase 1H — WhatsApp audit

Date: 2026-10-02. No provider secrets are recorded. The live BeyondTechWorld WhatsApp session was not disconnected, recreated, or used for a customer test. Campay was not changed. This phase was not deployed. Public signup was not closed.

## Current architecture

Outbound text, documents, and images go through `BeyondWasenderService`, which calls WaSender with the server-side API key and session id from configuration. `WhatsAppProviderInterface` covers send, session status, and groups. `WaSenderProvider` is the adapter behind that interface. Customer companies are not hardwired to WaSender in the new connection flow. A stub session provider sits beside it and never calls the network.

`cloud_whatsapp_connections` already stores one row per company: tenant, provider, provider connection id, phone, status, a credentials reference name, and health time. The reference is a config name, not the secret. Production status for the internal company is `ACTIVE`.

Incoming webhooks are processed by `WhatsAppWebhookProcessor`. `CloudWhatsAppConnectionResolver` chooses the company from the provider session id in the payload. It does not choose the company from the sender’s phone. If the session id is missing and more than one connected company exists, the webhook is rejected.

Contacts are unique per company and normalized phone, not globally. Conversations, messages, and MAI tool access use the active company context. Human handover sets the conversation mode to `HUMAN` on that company’s conversation. Queue jobs that still have a single internal connection keep that company. A customer send no longer falls through to the internal API key.

The company Messaging page shows WhatsApp as not connected and “Admin setup required” unless self-connection is explicitly switched on. That switch defaults to off.

## Official WaSender capability

Checked 2026-10-02 on WasenderApi’s own pages:

- Create session: `https://www.wasenderapi.com/api-docs/sessions/create-whatsapp-session`
- Connect and QR: `https://www.wasenderapi.com/api-docs/sessions/connect-whatsapp-session`
- Multiple sessions: `https://www.wasenderapi.com/help/whatsapp-sessions/managing-multiple-sessions`
- Plans: `https://www.wasenderapi.com/help/account-billing/subscription-plans`

One Wasender account can hold more than one WhatsApp session. Each session is one phone number. Creating a session requires an active subscription and is limited by the plan. The documented calls are:

- `POST /api/whatsapp-sessions` creates a session and can set a webhook URL and event list.
- `POST /api/whatsapp-sessions/{id}/connect` starts QR linking. The default method is QR. The success example status is `NEED_SCAN` and includes a `qrCode`.
- `GET /api/whatsapp-sessions/{id}/qrcode` returns the QR.
- The help page also describes restore, logout, and delete.

The create response includes a per-session `api_key` and `webhook_secret`. Those are server-side secrets. A personal access token is what creates sessions. Sending for a session uses that session’s own key, not another company’s key.

Documented webhook events include `messages.received`, `session.status`, `messages.update`, and a QR-updated event. The pages checked do not make the webhook body alone the proof that a session is connected. Connection is a provider status after the QR is scanned.

Plan session caps on the official plans page:

| Plan | WhatsApp sessions |
| --- | --- |
| Trial | 1, limited messages, 3 days |
| Basic | 1, unlimited messages |
| Pro | 3 |
| Plus | 6 |
| Business | 10, pricing says contact sales |

The plans page does not publish monthly prices for Trial, Basic, Pro, or Plus. It says payment is through Paddle and that more than the Business cap is a sales conversation. A separate marketing article has quoted dollar prices. Those figures are not on the plans page, so they are not treated as the contract.

This is not a separate Wasender subscription for every customer number. It is one Beyond subscription whose paid session slots are capped. BeyondTechWorld’s live number already occupies one slot. Each additional customer number needs another slot.

## Commercial gate

Messaging is priced at 5,000 XAF in `cloud_plans`. A 24-hour trial is free. The official plans page does not show a session price that can be compared with 5,000 XAF. A trial that created a real session would consume a paid slot before any subscription payment, and the chosen rule is not to destroy that session when the trial ends. Public self-connection is therefore not enabled.

PROVIDER COMMERCIAL MODEL: REQUIRES REVIEW

## Chosen behavior

- Customer connection uses a stub provider until Phase 1H-LIVE. No WaSender session is created from the application or from PHPUnit.
- QR data is cached for two minutes, returned only to that company’s owner, sent with `Cache-Control: no-store`, and not written to logs.
- `CONNECTED` is set only when the provider status says connected. Generating a QR does not connect.
- One connection row per customer. A repeat within three minutes reuses it. More than three attempts in an hour are refused.
- Trial expiry does not delete the connection, contacts, or conversations. It blocks new connection and outbound sends.
- Disconnect sets `DISCONNECTED`, writes an audit row, and keeps conversations.
- Customer outbound sends never use `services.whatsapp.wasender_api_key` or the internal session id.
- The internal connection row is left `ACTIVE` and is not rewritten by customer actions.
- MAI stays one engine. For a customer company, the system prompt adds that company’s name and tells the model to use only that company’s records. The assistant name stays MAI.

## Status mapping

Existing internal rows stay `ACTIVE`. Customer rows use `PROVISIONING`, `AWAITING_QR`, `CONNECTED`, `ERROR`, and `DISCONNECTED`. Webhook routing accepts `ACTIVE` and `CONNECTED`. Jobs that look for the single internal company still look at `ACTIVE` only, so a customer row does not take over Beyond’s announcements.
