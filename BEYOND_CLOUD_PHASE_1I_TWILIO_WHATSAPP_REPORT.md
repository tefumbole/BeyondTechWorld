# Beyond Cloud Phase 1I-Twilio — WhatsApp calling report

Date: 2026-10-03. This POC is WhatsApp Business Calling through Twilio Programmable Voice. It is not a normal telephone call. No second Twilio account was created. No number was purchased. The production WaSender session, webhook, and number were not changed. No live WhatsApp call was placed. Call recording stays off. Customer voice stays off. Campay and SMS were not touched.

## Account gate

The local project has no `.env` and no Twilio auth token. Production was not queried, so the existing account was not inspected from this machine. Nothing below was read from Twilio: account type, number country, voice capability, WhatsApp sender, WABA, business verification, or messaging limit.

Official Twilio documentation, read for this phase:

- https://www.twilio.com/docs/voice/whatsapp-business-calling
- https://www.twilio.com/docs/whatsapp/api/senders

A Twilio phone number is not a WhatsApp sender. Calling needs an onboarded WhatsApp sender, Meta Business Verification, a messaging limit of at least 2,000 business-initiated conversations in a rolling 24-hour period, and a TwiML application's Voice request URL saved as that sender's Voice Endpoint Configuration. If those are missing, Twilio says the Voice Endpoint Configuration cannot be saved.

Inbound calling is documented for WhatsApp senders in every Meta Cloud API country. Business-initiated calling is documented everywhere Cloud API is available except the United States, Canada, Egypt, Nigeria, Turkiye, and Vietnam, based on the business number's country. The consumer can be in any Cloud API country.

Calls from a WhatsApp endpoint cannot be connected to the public telephone network. Twilio rejects that path. This POC does not dial MTN, Orange, or any other PSTN number.

Because the account could not be confirmed, the voice application was not created and no sender's Voice Endpoint Configuration was changed. The live call was not run.

## What was implemented

`TwilioWhatsAppVoiceProvider` sits behind `VoiceProvider`. `StubVoiceProvider` is still the provider the rest of MAI uses. The Twilio class does not contain product, rental, or quotation logic.

A signed webhook is `POST /api/webhooks/twilio/whatsapp-voice`. The status webhook is `POST /api/webhooks/twilio/whatsapp-voice/status`. Both check `X-Twilio-Signature` (HMAC-SHA1 of the full URL plus the sorted form fields). An empty token or a bad signature returns 403 and creates no call. `From` and `To` are trusted only after that check.

The called address must be `whatsapp:+…`. That number selects `VoiceConnection` and the company. The caller's number does not. A normal telephone `From` is rejected and does not open a call. A customer-tenant connection is rejected. The MAI Voice file switch, default off, rejects the call with `<Reject/>` and does not start audio.

`CallSid` is stored on the call and a second webhook with the same id does not open another row. A `completed` status closes the call as `caller_hangup`.

TwiML never includes `<Record>` or `<Dial>`. Transfer of a telephone number returns `pstn_not_allowed`. The existing MAI path then offers a message or a callback and does not speak the configured number. Outbound WhatsApp calling is a method that always returns `outbound_disabled`. Twilio's `VOICE_CALL_REQUEST` template is the documented permission step for a later outbound call. It was not created and was not sent.

The realtime bridge is off. When it is off, an accepted webhook still does not open a media stream. It speaks a short apology and hangs up, so the caller is not left in silence and OpenAI is not called. A stream URL is emitted only if `voice.twilio.realtime_bridge` is turned on and a URL is set. Both stay off. OpenAI Realtime was not opened.

The same `VoiceGateway` and tool registry handle the turns: wedding date memory, catalogue tools, draft quotation wording, callback, message, and hangup. Prices are spoken only when a tool returns them.

## Tests

`TwilioWhatsAppVoiceTest`: 10 tests, 50 assertions, passing. They use a fake token and do not call Twilio or OpenAI.

`MaiVoiceTest`: 24 tests, 128 assertions, passing.

Covered here: valid signature, invalid signature, empty token, duplicate CallSid, called sender versus caller, kill switch, customer sender, PSTN request, known and unknown caller, multi-turn event, missing price, human request without a telephone transfer, message, callback, hangup, AI failure, caller hangup, and recording absent.

## Admin page

The MAI Voice page now says the Twilio WhatsApp sender is not connected and the realtime bridge is off. It shows sender, voice application, and bridge state, and it does not show a token. The page was not opened in a browser. This machine has no application environment to serve the ERP.

## Not run

No authorized tester called a WhatsApp sender. Latency, accent, names, dates, background noise, barge-in, and Twilio or OpenAI cost were not measured. WaSender delivery and MAI text were not rechecked on production because production was not contacted. The new route is separate from `/api/webhooks/wasender`.

## Final status

```
TWILIO ACCOUNT:
NOT ACTIVE

EXISTING TWILIO NUMBER:
NOT AVAILABLE

TWILIO WHATSAPP SENDER:
NOT ACTIVE

META BUSINESS VERIFICATION:
UNKNOWN

WHATSAPP CALLING ELIGIBILITY:
NOT DETERMINED

TWILIO VOICE APPLICATION:
NOT CONFIGURED

TWILIO WEBHOOK SECURITY:
PASS

TWILIO WHATSAPP VOICE PROVIDER:
IMPLEMENTED

OPENAI REALTIME:
NOT VALIDATED

REAL WHATSAPP CALL:
NOT RUN

MAI SPOKEN CONVERSATION:
NOT RUN

MULTI-TURN CONTEXT:
PASS

ERP TOOL CALLING:
PASS

EVENT REQUIREMENTS:
PASS

BARGE-IN:
NOT RUN

CALLBACK / MESSAGE:
PASS

HANGUP:
PASS

REAL CALL LATENCY:
NOT MEASURED

CALL RECORDING:
DISABLED

WASENDER PRODUCTION:
UNCHANGED

CUSTOMER VOICE:
DISABLED
```

Multi-turn, tools, the event workflow, callback, message, and hangup passed in the automated harness. They were not spoken on a live WhatsApp call.

Stopped. The Beyond WaSender number was not migrated.
