# Beyond Assistant — Conversational Upgrade

Date: 25 September 2026

The existing WhatsApp Hub assistant now answers ordinary messages in conversation, and still reads prices, stock, quotations, documents, attendance, and tenant balances only from the ERP tools. There is no second webhook, provider, or quotation store. Stage 9 was not started.

## What changed

- New conversations can start in AI when an admin turns on AI-first and the assistant is enabled. Saving that setting does not rewrite open human, paused, or closed chats. A confirmed bulk action can switch only eligible conversations. Assigned, paused, closed, pending verification, and open attendance check-ins stay as they are.
- Greetings include good afternoon, greetings, bonsoir, and salut. A known contact is greeted by name. An unknown contact is asked for their name once. The name and organization are stored on the WhatsApp contact and the open lead. A customer row is not created from a name.
- After checkout, check-in, OTP, documents, property, bills, and an explicit request for a person, other messages go through a conversational turn. The model may call one existing catalogue or quotation tool. The reply is normal language. Raw JSON is not sent.
- A previous quotation is read only for the recognized customer. Historical prices are labeled historical. A new draft still uses current catalogue prices and staff approval.
- A manual staff reply takes the conversation out of AI, assigns that person, and records `HUMAN_TAKEOVER_BY_REPLY` before the message is sent. The assistant re-reads the mode before every AI send and drops the text if a person has taken over.
- “Call me” creates a `whatsapp_call_requests` row. The assistant does not claim the call was placed. Handover assigns `default_handover_user_id` when that user exists.
- Approved knowledge rows describe sound, lighting, screens, stage, technical support, and the company. They do not contain stock or prices.

## Tests

`./vendor/bin/phpunit --filter WhatsApp` — 140 tests, 782 assertions, OK.

## Production

AI-first stays off until an admin enables it. Deploy with the existing script and restart only `beyondtechworld-whatsapp-queue`.
