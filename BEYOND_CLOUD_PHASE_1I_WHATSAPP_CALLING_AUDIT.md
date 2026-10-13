# Beyond Cloud Phase 1I-WA — WhatsApp calling audit

Date: 2026-10-03. WhatsApp calls only. No Telnyx, Twilio, SIP trunk, or PSTN number was purchased or connected. The BeyondTechWorld WaSender session was not changed, disconnected, or re-registered. Production webhooks were not moved. No live WhatsApp call was answered by MAI. The MAI voice stub from Phase 1I remains the only automated voice path, and it does not claim a live call.

The earlier PSTN provider review is not the plan for this requirement. There is no `TelnyxVoiceProvider` and none will be added for WhatsApp calling.

## The question that decides MAI on the current provider

Can an application receive the actual audio of an active WhatsApp call through WaSender and send realtime audio back into that same call?

**NOT DOCUMENTED.**

The published WaSender API does not describe accept, inbound call audio, outbound call audio, or a bidirectional media stream. An incoming-call webhook is not that capability. MAI cannot answer a WhatsApp call on the current provider from the documentation that exists today.

## What Beyond already does

`WhatsAppWebhookProcessor::recordCall()` stores webhook types `call` and `calls.received` on `whatsapp_calls`. That row is a log: caller, provider call id, received or missed. It does not accept the call, open media, or speak.

`POST /api/send-message` with `audioUrl` sends a voice note. A voice note is not a live call. This phase does not use it as a substitute.

The company is still chosen from the WhatsApp connection, not from the caller’s phone. That rule stays. Customer-tenant voice stays disabled. Call recording stays disabled.

## WaSender, from the current official docs

Sources read on 2026-10-03:

- Call webhook: https://wasenderapi.com/api-docs/webhooks/webhook-call-received
- Session update (`auto_reject_calls`): https://wasenderapi.com/api-docs/sessions/update-whatsapp-session
- Send audio (voice note, not a call): https://wasenderapi.com/api-docs/messages/send-audio-message
- Published API index, which lists one call webhook and no call-media endpoint

The `call` webhook fires for an incoming voice or video call. The example payload is an event named `call` and a `data.call` object with `id`, `from`, `date`, `isGroup`, `isVideo`, and `status: "offer"`. The page says the call id is required for rejecting the call, and that the event is useful for automatic rejection or for logging. It does not show an answer action, an audio payload, SDP, or a media URL.

`auto_reject_calls` on the session update is a boolean. When enabled, the session rejects incoming calls. That is a session setting, not an API to join a call.

No published endpoint was found for accept, hangup of one call, inbound audio, outbound call audio, WebRTC, SDP, transfer, mute, or placing a call. The webhook text mentions rejection, but it does not publish a per-call reject URL. This audit does not guess at unpublished routes.

| Capability | WaSender |
| --- | --- |
| A. Incoming call notification | SUPPORTED. Webhook event `call`. |
| B. Accept / answer | NOT DOCUMENTED |
| C. Reject | Session flag `auto_reject_calls` is SUPPORTED. A per-call reject endpoint is NOT DOCUMENTED. |
| D. Hang up an active call | NOT DOCUMENTED |
| E. Incoming realtime call audio | NOT DOCUMENTED |
| F. Outgoing realtime call audio | NOT DOCUMENTED. `audioUrl` is a voice note. |
| G. Bidirectional media streaming | NOT DOCUMENTED |
| H. WebRTC / media bridge | NOT DOCUMENTED |
| I. Call session identifier | SUPPORTED. `data.call.id`. |
| J. Call state events | The published example is `status: "offer"` only. Accept, hangup, and missed events are NOT DOCUMENTED. |
| K. Call transfer | NOT DOCUMENTED |
| L. Mute / unmute | NOT DOCUMENTED |
| M. Caller hangup detection | NOT DOCUMENTED |
| N. Initiate a WhatsApp call | NOT DOCUMENTED |
| O. Receive a WhatsApp call through the API | The notification is SUPPORTED. Joining the call is NOT DOCUMENTED. |

WASENDER AI CALL ANSWERING: **NOT SUPPORTED** by the published API. No `WaSenderVoiceProvider` was written. Undocumented client behavior was not probed.

## Meta WhatsApp Business Calling API

Sources read on 2026-10-03:

- Calling overview: https://developers.facebook.com/documentation/business-messaging/whatsapp/calling
- User-initiated calls: https://developers.facebook.com/documentation/business-messaging/whatsapp/calling/user-initiated-calls
- Call reference: https://developers.facebook.com/docs/whatsapp/cloud-api/calling/reference/
- Calling FAQ: https://developers.facebook.com/documentation/business-messaging/whatsapp/calling/faq
- User call permissions: https://developers.facebook.com/documentation/business-messaging/whatsapp/calling/user-call-permissions
- Pricing: https://developers.facebook.com/documentation/business-messaging/whatsapp/calling/pricing
- Business-app coexistence: https://developers.facebook.com/documentation/business-messaging/whatsapp/embedded-signup/onboarding-business-app-users

Meta’s Calling API is a Cloud API feature. It uses `POST /{phone-number-id}/calls` with actions `connect`, `pre_accept`, `accept`, `reject`, and `terminate`. Media is WebRTC. The business supplies RFC 8866 SDP. User-initiated calls deliver a Call Connect webhook that contains Meta’s SDP offer. The business returns an SDP answer on `pre_accept` and then `accept`. There is about 30 to 60 seconds to accept. The documented media path is one audio stream, send and receive, with an 8000 Hz clock rate on Meta’s SDP. After accept, audio flows on the WebRTC peer connection, not as a WhatsApp voice note.

Business-initiated calls use action `connect` plus an SDP offer, and only after the user has granted call permission. The overview says user-initiated calling is available everywhere Cloud API is available. Business-initiated calling is not available when the business number’s country is the United States, Canada, Egypt, Vietnam, or Nigeria. The consumer’s country can be any Cloud API country. User-initiated calls are free. Business-initiated calls are billed in six-second pulses and require a payment method. This phase places neither kind.

Call state arrives on webhooks: connect, status, and terminate. Caller hangup produces a terminate webhook. The business hangup is action `terminate`.

Human handover is not a native transfer. The Calling FAQ says Meta does not know about separate agents. A partner can move media from one agent to another behind the partner’s own media server while the Meta leg stays up. There is no documented PSTN transfer and no documented “bridge this call to a mobile phone” action. Mute is not a documented Calling API action.

Recording and transcription exist as Meta features and are described as free on top of call rates. Beyond still keeps call recording disabled. Those Meta features are not turned on.

Eligibility, from the calling overview:

1. The business number is on Cloud API, and not on the WhatsApp Business app.
2. The app is subscribed to the `calls` webhook field, unless SIP signaling is used.
3. The same app is subscribed to the WhatsApp Business account.
4. The app has `whatsapp_business_messaging`.
5. The business has a daily messaging limit of at least 2,000 unique recipients.
6. Calling is enabled on that phone number.

SIP is an optional signaling mode on the same Calling API. Enabling SIP turns off the calling HTTP endpoints and calling webhooks. It is still Meta calling, not a reason to buy a PSTN number. It was not configured.

| Capability | Meta official Calling API |
| --- | --- |
| Incoming call event | SUPPORTED. Call Connect webhook. |
| Accept call | SUPPORTED. `pre_accept` then `accept`, with SDP. |
| Reject call | SUPPORTED. Action `reject`. |
| Call state | SUPPORTED. Connect, status, and terminate webhooks. |
| Inbound audio | SUPPORTED. WebRTC after the SDP answer is accepted. |
| Outbound audio | SUPPORTED. Same WebRTC connection, send and receive. |
| Bidirectional realtime | SUPPORTED. One audio track. |
| WebRTC | SUPPORTED. |
| SDP / media negotiation | SUPPORTED. RFC 8866. |
| Hangup | SUPPORTED. Action `terminate`, plus a terminate webhook when the user hangs up. |
| Business-initiated call | SUPPORTED only with user permission, and not for business numbers in the United States, Canada, Egypt, Vietnam, or Nigeria. |
| User-initiated call | SUPPORTED where Cloud API is available. |
| Webhook | SUPPORTED. |
| AI audio bridge possible | SUPPORTED only after this WebRTC leg exists. OpenAI would sit behind the business media bridge and the existing MAI tool registry. It was not connected. |
| Native call transfer to a person or a phone | NOT a Meta action. Partner-side media handoff only, and only once the WebRTC leg exists. |
| Mute | NOT DOCUMENTED as an API action. |

META WHATSAPP CALLING: **SUPPORTED** as a Cloud API product.

META REALTIME MEDIA: **SUPPORTED** through WebRTC and SDP on that product.

## Comparison

| Capability | WaSender | Meta official |
| --- | --- | --- |
| Incoming call event | SUPPORTED | SUPPORTED |
| Accept call | NOT DOCUMENTED | SUPPORTED |
| Reject call | Session-wide auto-reject SUPPORTED. Per-call reject NOT DOCUMENTED | SUPPORTED |
| Call state | `offer` example only | SUPPORTED |
| Inbound audio | NOT DOCUMENTED | SUPPORTED via WebRTC |
| Outbound audio | NOT DOCUMENTED for a live call | SUPPORTED via WebRTC |
| Bidirectional realtime | NOT DOCUMENTED | SUPPORTED |
| WebRTC | NOT DOCUMENTED | SUPPORTED |
| SDP / media negotiation | NOT DOCUMENTED | SUPPORTED |
| Hangup | NOT DOCUMENTED | SUPPORTED |
| Business-initiated call | NOT DOCUMENTED | SUPPORTED, with the country and permission limits above |
| User-initiated call | Notification only | SUPPORTED |
| Webhook | SUPPORTED | SUPPORTED |
| AI audio bridge possible | NOT DOCUMENTED | SUPPORTED only on a Cloud API calling number |

## Coexistence with the current WaSender number

This is the stop.

WaSender’s own linking docs use a WhatsApp Web companion session (`web.whatsapp.com` passkey linking, QR connect). That is not Cloud API.

Meta’s Calling overview requires the number to be on Cloud API and not on the WhatsApp Business app.

Meta’s coexistence guide, for a number that stays on the WhatsApp Business app and also uses Cloud API, says voice and video calls are **not supported on Cloud API**. The Business app’s own call button is unchanged, which means calls stay on the phone app. The same guide says onboarding unlinks every companion device. Companions can be re-linked afterward, but that does not add Cloud API call media.

So the documented choices for this number are:

- Stay on WaSender. Messaging continues. Call webhooks can be logged. MAI cannot hear or speak on the call.
- Move the number to Cloud API-only calling. That is a migration off the current WaSender session: unregistering the linked session, registering the number on Cloud API, moving webhook ownership, and re-testing every text, media, delivery, and MAI path. Calling still cannot be added as a second product beside WaSender.

Two Cloud API apps can split chat and calling on one Cloud API number. The Calling FAQ says that. Both apps are Cloud API. That does not let WaSender keep the session while Meta carries the audio.

No migration was done. The Beyond number and the WaSender webhook stay where they are.

## Recommended path

Do not answer WhatsApp calls with MAI on WaSender. Keep logging the call event. Do not reject live calls from this work, and do not turn on `auto_reject_calls`; that would change production call behavior.

Do not connect OpenAI Realtime to a WhatsApp call until a documented bidirectional media leg exists. It does not exist on the current session.

Do not start PHASE 1I-WA-LIVE. Meta’s media path is real, and using it on this number requires leaving WaSender. That decision is separate from this audit.

The MAI voice kill switch stays off. It already stops the stub telephone gateway. It does not, and must not, start answering WhatsApp calls. Customer voice stays disabled. Recording stays disabled. The stub tests remain the only voice tests.

If a later phase is allowed to move the Beyond number to Cloud API, the work before any call is a messaging migration plan: what happens to the live session, templates, delivery receipts, MAI text, human takeover, and the webhook. That plan is not this phase.

## Final status

```
VOICE REQUIREMENT:
WHATSAPP CALLS ONLY

PSTN/SIP TELEPHONY:
NOT REQUIRED

WASENDER INCOMING CALL EVENT:
SUPPORTED

WASENDER CALL ACCEPT:
NOT DOCUMENTED

WASENDER INBOUND CALL AUDIO:
NOT DOCUMENTED

WASENDER OUTBOUND CALL AUDIO:
NOT DOCUMENTED

WASENDER BIDIRECTIONAL REALTIME MEDIA:
NOT DOCUMENTED

WASENDER AI CALL ANSWERING:
NOT SUPPORTED

META WHATSAPP CALLING:
SUPPORTED

META REALTIME MEDIA:
SUPPORTED

CURRENT WASENDER MESSAGING:
UNCHANGED

CURRENT BEYOND WHATSAPP NUMBER:
UNCHANGED

MAI VOICE STUB:
PRESERVED

LIVE WHATSAPP AI CALL:
NOT ENABLED

CUSTOMER VOICE:
DISABLED

CALL RECORDING:
DISABLED
```

Stopped. PHASE 1I-WA-LIVE was not started.
