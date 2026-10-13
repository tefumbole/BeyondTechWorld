# Beyond Cloud Phase 1I — Voice provider review

Date: 2026-10-03. Pages below were the official sources reviewed for this phase. Prices and Cameroon inventory change. Nothing was purchased. No carrier account was opened. No number was ordered.

Selected telephony provider for a live number: **NOT SELECTED**.

The code speaks only to an in-process stub. A live adapter is not registered. Setting a provider name in configuration does not open a socket.

## What the call has to do

The provider must receive an inbound call, let our server accept or reject it, carry caller audio and assistant audio, transfer, hang up, report status on webhooks, and support DTMF where the caller needs it. Cameroon or another international number must be possible, with SIP if we terminate toward OpenAI. The model must not receive carrier credentials.

## OpenAI Realtime, reviewed, not validated on a call

Official guides:

- https://developers.openai.com/api/docs/guides/realtime-sip
- https://developers.openai.com/api/docs/guides/voice-sip
- https://developers.openai.com/api/docs/guides/realtime-server-controls

Documented production shape:

- A SIP trunk sends the call to `sip:$PROJECT_ID@sip.api.openai.com;transport=tls` (EU host `sip-eu.api.openai.com` when that region is used).
- OpenAI sends a project webhook `realtime.call.incoming` with SIP From, To, and Call-ID.
- Our server accepts with `POST https://api.openai.com/v1/realtime/calls/{call_id}/accept`, body type `realtime`, a realtime model, instructions, tools, and a voice. The accept reference lists current realtime models including `gpt-realtime` and `gpt-realtime-2` family names. Those names must be re-read on the day a live call is approved. This phase does not pin a model by memory.
- Audio can stay on the provider-to-OpenAI SRTP path. Our server watches the call on the sideband WebSocket `wss://api.openai.com/v1/realtime?call_id={callId}` and answers tool calls there. The model still does not get the database, SQL, or secrets.
- Transfer is `POST /v1/realtime/calls/{call_id}/refer` with `target_uri` such as `tel:+…` or `sip:agent@example.com`.
- Reject can return a SIP status, for example 486.
- Turn detection and interruption are part of the realtime session. They were not measured here, because no call was placed.

This is the architecture to use when a trunk exists. It was not executed. OpenAI Realtime voice is **NOT VALIDATED**.

A second documented pattern is a carrier media stream (Twilio bidirectional Media Streams, Telnyx bidirectional streaming, Vonage NCCO websocket) bridged by our own gateway. That puts audio on our server. The SIP-to-OpenAI path keeps audio off our server and keeps tools on the sideband. The sideband path matches “one MAI, tools stay in Laravel” more closely. It still needs a carrier that can SIP-trunk the call. That carrier is not chosen.

## Twilio

Reviewed:

- Media Streams: https://www.twilio.com/docs/voice/media-streams
- Cameroon SIP trunking pricing: https://www.twilio.com/en-us/sip-trunking/pricing/cm

What those pages support:

- Programmable Voice can answer, gather DTMF, transfer, and hang up through TwiML and webhooks.
- Bidirectional Media Streams use a WebSocket. Inbound audio on that stream is mulaw at 8 kHz. A `clear` message drops buffered outbound audio, which is the barge-in control for that transport.
- Call status webhooks exist.
- The Cameroon SIP trunking pricing page reviewed for this session says Twilio does not have voice-enabled numbers in that locale. It lists international numbers from other locales starting at $1.15 per month, a Cameroon route starting at $0.4060 per minute, and Cameroon mobile at $0.7833 per minute. Local Cameroon inventory would have to be checked again with AvailablePhoneNumbers before anyone treats that page as a purchase decision.
- OpenAI’s own SIP guide uses Twilio as an example trunk. That example is not a decision to buy Twilio.

Account limits for concurrent calls were not read from a Beyond Twilio account, because there is no such account in this phase.

## Telnyx

Reviewed:

- Cameroon numbers: https://telnyx.com/phone-numbers/cameroon
- Cameroon DID requirements: https://support.telnyx.com/en/articles/9959347-cameroon-did-requirements (article date 2024-10-15)
- Call Control streaming is documented under Telnyx Voice / Call Control (`stream_url`, bidirectional RTP over WebSocket, plus transfer, hangup, and record commands).

What those pages support:

- Call Control can answer, stream audio both ways, transfer, hang up, and send webhooks.
- SIP domains are on `*.sip.telnyx.com`, which can target OpenAI’s SIP host if a later phase chooses that path.
- The marketing page reviewed here says local Cameroon numbers start at $27.50 per month. That is a listed starting price, not a quote and not an order.
- The requirements article says Cameroon DIDs need identity or company documents and proof of address, with validation on the order of 72 hours. A number is therefore not instant even if the catalogue shows one.
- Inbound channel capacity is an account setting. It was not measured.

Recording commands exist on the provider. This phase does not enable them.

## Vonage

Reviewed: Voice NCCO reference, https://developer.vonage.com/en/voice/voice-api/ncco-reference

What that reference supports:

- NCCO actions include `talk`, `input`, `connect` (phone or WebSocket), `transfer`, `record`, and `notify`.
- WebSocket audio is documented as `audio/l16` at 16 kHz or 8 kHz, with 24 kHz also described.
- `bargeIn` on talk or stream stops playback when the caller speaks.
- Transfer moves legs to another `conversation_id`.
- Webhooks report status.

Cameroon DID availability was not stated on the pages reviewed. This review does not claim Vonage sells a Cameroon number.

## Comparison

| Need | Twilio | Telnyx | Vonage | OpenAI SIP |
| --- | --- | --- | --- | --- |
| Inbound control | Yes, Programmable Voice | Yes, Call Control | Yes, NCCO | Accept or reject after the trunk delivers the call |
| Audio to an AI | Media Streams, or SIP onward | Stream URL, or SIP onward | WebSocket connect, or SIP if the account has it | Native once the trunk targets `sip.api.openai.com` |
| Transfer | Yes | Yes | Yes | `refer` to `tel:` or `sip:` |
| Hangup | Yes | Yes | Yes | Yes |
| Webhooks | Yes | Yes | Yes | `realtime.call.incoming` plus call events |
| Cameroon number on the page reviewed | Pricing page says no voice-enabled numbers in that locale | Marketing page lists local DIDs from $27.50/month, with KYC | Not confirmed | OpenAI does not sell the DID; the trunk provider does |
| Fit with existing MAI tools | Sideband or our bridge | Sideband or our bridge | Sideband or our bridge | Sideband WebSocket is the documented tool path |
| Bought for Beyond | No | No | No | No |

## Decision

No provider is selected.

Telnyx is the only one of the three whose current public pages both advertise a Cameroon DID and document the identity checks. Twilio is the trunk OpenAI documents in its example, and its Cameroon page says it does not offer voice-enabled numbers there. Vonage’s call control is capable, and a Cameroon number was not confirmed. Any of the three could SIP-trunk into OpenAI later. Choosing one requires a fresh inventory check, a written price, and an explicit request to open an account. This phase stops before that.

Concurrency is not assumed. The stub allows more than one answered call, capped by `voice.max_concurrent` (default 2). A live account’s channel limit is unknown until a provider is chosen.
