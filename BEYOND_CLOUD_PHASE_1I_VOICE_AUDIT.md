# Beyond Cloud Phase 1I — Voice audit

Date: 2026-10-03. This is an audit of the code that already exists. No phone number was purchased. The live BeyondTechWorld business number was not pointed at an AI. WhatsApp sending and the WaSender session were not changed. Campay was not changed. Public signup was not closed.

## 1. Existing call-related code

There is no programmable-voice stack in the ERP today.

What exists is a log of WhatsApp call notifications:

- `WhatsAppWebhookProcessor::recordCall()` handles webhook types `call` and `calls.received`.
- It dedupes on `provider_call_id`, finds or creates a WhatsApp contact from the caller phone, and stores a row on `whatsapp_calls` through `App\WhatsApp\WhatsAppCall`.
- Status is `RECEIVED`, or `MISSED` when the payload says timeout, reject, rejected, or missed. Staff can later mark follow-up, contacted, or resolved from the WhatsApp hub.
- The model is not the voice assistant. It does not answer, speak, transfer, or hang up a telephone call.

`WhatsAppCallRequest` and `WhatsAppCallRequestService` are a different feature. They record a text-chat request such as “please call me”, opened from `BeyondAssistantService`. That is not a PSTN call and was left as it is.

`tests/Feature/WhatsAppWebhookTest::test_incoming_call_and_duplicate_call` still covers the log. It was run again after the voice work and passed (1 test, 5 assertions). The webhook processor does not reference the new voice gateway.

## 2. WaSender incoming-call logging

WaSender is the live WhatsApp provider for the internal company. Incoming call events are stored and shown under WhatsApp Calls. They are not bridged to a media stream, and MAI does not speak on them.

This phase does not replace that logger. Telephone calls, when a later phase connects a number, go to new `voice_connections` / `voice_calls` tables.

## 3. Current MAI architecture

There is one assistant, not one brain per channel.

- `ConversationalTurnService` is the turn engine for WhatsApp and the website. It asks `AiProviderInterface` for a completion, then runs tool calls through `AssistantToolExecutor`.
- `AssistantToolRegistry` is the approved tool list (catalogue search, rental availability, event solution, quotations, internship, attendance, appointments, human handover, and others). Each tool has a sensitivity: `PUBLIC`, `RECOGNIZED`, `VERIFIED`, or `OWNER`.
- `AssistantToolSelector` builds the OpenAI tool schema from that registry.
- `BeyondAssistantSystemPromptBuilder` builds the system prompt, including the contact voice line (what to call someone, relationship, language, tone).
- A website conversation is `WhatsAppConversation::CHANNEL_WEBSITE`. Everything else on that model was WhatsApp. Voice is now a third channel value, `voice`, with `assistantChannel()` so a later live turn is not mislabeled as WhatsApp.

Human handover today is the tool `request_human_handover`. It sets the conversation mode to `HUMAN`. It does not transfer a telephone call. That remains true for WhatsApp and the website.

## 4. Current OpenAI integration

Text MAI calls the Chat Completions endpoint. Configuration is `config/assistant.php`: provider default `openai`, URL default `https://api.openai.com/v1/chat/completions`. The key stays in configuration. It is not given to a voice model as a tool.

Nothing in the current text path opens a Realtime session, a SIP trunk, or an audio socket. Realtime voice was not validated against a live call in this phase.

## 5. Available conversation tools

The registry already covers the workflows this phase must reuse, including:

- `search_rental_products`, `check_rental_availability`, `get_rental_product_information`
- `build_event_solution` and the package, extras, stage, screen, and transport tools
- `create_event_quotation_draft` (draft only; it does not final-approve)
- `get_company_information`, appointment tools
- `get_customer_payment_summary` at sensitivity `VERIFIED`
- owner tools such as `set_ai_enabled` at sensitivity `OWNER`

Voice does not get a second copy of this list. `VoiceToolBridge` calls `AssistantToolRegistry::get()`. Unknown names, names that look like SQL or a shell, `VERIFIED` tools, and `OWNER` tools are refused. `RECOGNIZED` tools are refused unless the call is verified. A matching caller ID does not set verified.

The default runner does not call `AssistantToolExecutor`, so a stub call cannot write a sale, a payment, or a quotation row. Tests supply a runner and assert the tool name and the arguments. A later live phase should pass the same names into the existing executor.

## 6. Human handover

WhatsApp and website handover stays `request_human_handover` and conversation mode `HUMAN`.

Voice adds separate controlled actions, because a phone transfer is not a chat tool:

- `transfer_call` — only the number stored on the voice connection, never a number the caller speaks
- `request_callback` — a `voice_callbacks` row; the reply does not read the transfer number aloud
- `take_message` — used when transfer fails
- `end_call` — provider hangup after the goodbye line

After a successful transfer, handling becomes `HUMAN`, the linked conversation mode becomes `HUMAN`, and a further caller utterance gets silence from MAI.

## 7. Tenant routing

Cloud data is chosen by `CloudTenantContext`. WhatsApp chooses the company from the provider session, not from the sender’s phone.

Voice follows the same rule with a different key. The called number matches `voice_connections.called_number`. That row carries `cloud_tenant_id`. The caller’s number is never used to pick the company. An unknown called number is rejected with no tenant. A `CUSTOMER` connection is rejected even if configuration tries to allow customer voice. Only `INTERNAL` is answered in this phase, and only when the kill switch is on.

## 8. Business phone architecture

The business can already be reached on WhatsApp through the internal WaSender session (connection id 1, provider wasender, tenant 1, status ACTIVE). That session was not read for secrets and was not modified.

The ERP also stores a company phone on the internal tenant settings. That field is not a programmable voice route. There is no SIP trunk, no purchased DID for MAI, and no webhook from a telephone carrier.

Phase 1I adds the tables and the stub gateway only. Applying the migration on production was not part of this phase.
