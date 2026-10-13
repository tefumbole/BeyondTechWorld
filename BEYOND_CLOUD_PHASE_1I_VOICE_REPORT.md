# Beyond Cloud Phase 1I — Voice architecture report

Date: 2026-10-03. Audit, provider review, and a stub implementation only. No number was purchased. The live business number was not connected. No customer call was placed. This was not committed and not deployed. The migration `2026_10_03_190000_create_mai_voice.php` was not run on production.

## Selected architecture

```
Telephone network
    → carrier / SIP  (not connected)
    → VoiceProvider
    → VoiceGateway
    → existing MAI tool registry
    → tenant ERP, only through an approved tool
```

The return path is the same gateway speaking back through the provider. The voice model does not receive SQL, a database shell, the filesystem, or provider secrets.

Phase 1I implements `VoiceProvider` with `StubVoiceProvider` only. The stub records transfer and hangup in memory. It never opens a network socket.

When a later phase connects a trunk, the intended live shape is the one OpenAI documents: the carrier SIP-trunks the call to `sip.api.openai.com`, our server accepts the webhook, and tool calls arrive on the sideband WebSocket. Those tool calls must use `AssistantToolRegistry` and, once approved, `AssistantToolExecutor`. They must not use a second business brain. That live path is not wired. If `voice.engine` is set to `conversational`, the gateway apologizes and offers a transfer or a message. It does not call OpenAI.

The scripted turn (`VoiceScriptedTurn`) is the test harness. It keeps call state and may only speak a price, a product, or an event fact that the tool bridge returned. It is not the production reasoning engine.

## Provider

**NOT SELECTED.** See `BEYOND_CLOUD_PHASE_1I_VOICE_PROVIDER_REVIEW.md`.

The gateway stores `provider = stub` on every call. A configuration value cannot swap in Twilio, Telnyx, or Vonage in this phase.

## OpenAI voice

**NOT VALIDATED.** No Realtime session was opened. No SIP INVITE was sent. Text MAI remains on Chat Completions and was not pointed at a telephone call.

Documented capabilities we are designing toward, and did not execute: realtime speech in and out, tool calls on the sideband, server-side conversation state, SIP accept or refer or hangup, turn detection, and barge-in. The stub has no audio stream, so barge-in was not exercised. The live path should use the carrier or Realtime interruption control (`clear` on Twilio Media Streams, `bargeIn` on Vonage, Realtime turn detection on the OpenAI SIP path) rather than an IVR menu.

## Call routing

`VoiceGateway::incoming($called, $caller, $providerCallId)`:

1. If the kill switch is off, store `REJECTED` / `voice_off` and do not speak.
2. Find an `ACTIVE` `voice_connections` row by the normalized called number. No row means `unknown_number` and a null tenant.
3. If that row has AI disabled, reject `ai_disabled`.
4. If `tenant_type` is not `INTERNAL`, reject `customer_voice_disabled`. Caller ID is not consulted.
5. Refuse when answered calls are already at `voice.max_concurrent`, or when this caller is already at `voice.max_calls_per_caller_per_hour` on that connection.
6. Normalize the caller with `WhatsAppPhone::sanitizeForStorage`. A single contact on that same tenant may supply a display name for “Welcome back”. Two matches, or a match on another tenant, do not. `identity_verified` stays false.
7. Open a `whatsapp_conversations` row with `channel = voice` when those tables exist, greet, and store the greeting in the transcript.

The greeting tells the caller they are talking to MAI, the virtual assistant. It does not claim to be a human employee. Unknown callers are asked for a name. After-hours wording comes from the language pack when the connection has business hours and the clock is outside them. English and French packs exist for the fixed lines. The scripted harness replies used in tests are English; a live Realtime session is what will follow the caller’s language. The core greeting path reads `config/voice.php` by language instead of a single hardcoded English string.

## Tenant routing

Called number → `VoiceConnection` → `cloud_tenant_id`.

Customer companies cannot be answered. `MaiVoiceStatus` reports `customer_voice` false. The gateway does not read `voice.customer_voice` to open that door. A future company can still have a connection row (number, greeting, hours, transfer number, assistant name). Answering it is a later phase.

## MAI integration and tools

`VoiceToolBridge` is the only way the harness reaches a business tool. It allows a `PUBLIC` registry tool. It refuses `RECOGNIZED` tools without verification, and it always refuses `VERIFIED` and `OWNER` tools. Payment summary, bank details, password reset, and ownership changes are refused. “I paid” does not confirm a payment and does not call a tool.

Event memory is on `voice_calls.state`. “Sound for a wedding”, then “November 14”, then a venue, calls `build_event_solution` with that type, date, and venue. A quotation utterance calls `create_event_quotation_draft` and the spoken line says the draft is not confirmed and still needs approval. The stub runner does not insert a quotation row.

`AssistantToolExecutor` is intentionally not called yet, so the stub cannot write ERP documents. The tool name, arguments, and authorization are what this phase proves.

## Human transfer, callback, message, hangup

Transfer dials `voice_connections.transfer_number` only. A number in the caller’s sentence is ignored and is not spoken back. Success sets handling to `HUMAN` and MAI does not speak again on that call. Failure leaves MAI on the call, says the team is not available, and stores the next utterance as a message (caller, phone, message, time). “Ask Roland to call me” creates a callback for the name Roland and does not disclose the configured number.

Goodbye calls `StubVoiceProvider::hangup`. Provider exceptions are caught on the voice call. They do not change WhatsApp message rows.

## Privacy and recording

Recording is forced off on every create, hear, and hangup. `config('voice.recording')` is ignored. The admin page shows Recording: Disabled. There is no silent recorder.

Transcripts, when `voice.transcribe` is on, store caller lines, assistant lines, and action names. They do not store chain-of-thought. The admin detail page shows that transcript and the call metadata. It does not show a transfer number or an API key.

The greeting is the AI disclosure. Transcription of a live caller should not be turned on for a real number until the notice and retention rules for that jurisdiction are accepted. The stub transcripts are test data.

## Security

- Caller ID is a lookup signal. It is not authorization.
- High-risk tools stay on their existing sensitivity. Voice does not add a weaker path.
- Campay and subscription payment stay disabled. Voice cannot mark a payment successful.
- SMS stays optional and deferred. This phase does not send SMS.
- WhatsApp webhooks still only log calls. They do not enter `VoiceGateway`.

## Cost, abuse, concurrency

Each call stores start, answer, end, and duration. Telephony and AI cost fields are not invented, because no carrier bill and no Realtime usage exist yet. The admin snapshot sums duration for the day and raises `daily_alert` when it passes `voice.daily_seconds_alert` (default 3600 seconds).

Safeguards in the stub: max duration (default 600 seconds), max concurrent answered calls (default 2), max calls per caller per hour (default 8), and three silent turns then hangup. The stub can hold more than one answered call. A live provider’s channel cap is not known.

## Failure

AI failure and the unconnected conversational engine speak an apology, transfer if a number is configured, otherwise take a message. They do not throw into WhatsApp. A provider exception on transfer sets `provider_down` on that voice call only.

An OpenAI outage on a future voice session must stay inside this gateway. The text assistant is a different call path and was not switched over.

## Admin

`/admin/mai-voice` is linked for platform roles 1 and 2 only. It is not in the company WhatsApp menu. The page shows status, provider, business number, AI enabled, whether a transfer number is configured (not the digits), business hours, recent calls, failed calls, average duration, and transfer requests.

The kill switch is `storage/app/mai-voice`. `1` is on, `0` is off. A missing file uses `MAI_VOICE_ENABLED`, which defaults to off. Turning it off does not need a deploy. Company role 5 receives 403 on the switch action.

The admin blade was not opened in a browser in this phase. The status payload and the role check were tested. The layout link is present for roles 1 and 2.

## Tests

`laravel-app/tests/Feature/MaiVoiceTest.php`: 24 tests, 128 assertions, passing.

Covered: kill switch, unknown caller, known caller, ambiguous caller, caller ID not choosing the tenant, payment summary refused, payment claim not confirmed, voice conversation on the called number, multi-turn wedding date, product, price, rental availability, no invented catalogue data, quotation draft without a quotations insert, transfer success and MAI silence, transfer to a spoken number ignored, transfer failure and message, callback without a private number, hangup, AI failure leaving a WhatsApp row untouched, provider failure isolated, conversational flag falling back without a network call, duration, silence, concurrency, repeat callers, customer connection refused, unknown number, French after-hours greeting, latency recorded, recording forced off, admin role check.

`WhatsAppWebhookTest::test_incoming_call_and_duplicate_call`: 1 test, 5 assertions, passing.

In-process `hear()` latency in that test was under 2000 ms. That is not a telephone round trip. End of caller speech to first audio packet was not measured, because no call was placed.

## Limitations

- No live DID, no SIP trunk, no Realtime session.
- Barge-in is not implemented in the stub. It depends on the live audio path.
- `AssistantToolExecutor` is not on the voice path yet, so ERP rows are not read or written by a call.
- Scripted replies are a harness. They are not MAI’s live wording.
- Fixed lines are English and French. Other languages need a pack plus a realtime model that can speak them.
- The admin screen was not clicked through in a browser.
- Production migration was not applied. With the switch defaulting off, deploying later still would not answer a call until a connection row exists and the switch is turned on. That deploy is not part of this phase.

## Final status

```
PHASE 1I ARCHITECTURE: PASS

SELECTED TELEPHONY PROVIDER: NOT SELECTED

OPENAI REALTIME VOICE: NOT VALIDATED

INBOUND CALL: IMPLEMENTED IN TEST

MAI CONVERSATION: PASS

ERP TOOL CALLING: PASS

EVENT REQUIREMENTS: PASS

HUMAN TRANSFER: IMPLEMENTED IN TEST

CALLBACK REQUEST: PASS

CALLER ID SECURITY: PASS

TENANT ISOLATION: PASS

VOICE KILL SWITCH: PASS

CALL RECORDING: DISABLED

LIVE BUSINESS NUMBER: NOT CONNECTED

PUBLIC CUSTOMER VOICE: DISABLED

PRODUCTION PAYMENT: DISABLED

SMS: OPTIONAL / DEFERRED
```

Stopped here. The next phase is PHASE 1I-LIVE: one controlled inbound call. It has not been started.
