# Beyond Cloud Phase 1I-Twilio live configuration

Date: 2026-10-03. The supplied Twilio account was checked with Twilio’s account API. Configuration stopped there. No second account was created. No number was purchased. WaSender was not changed. MAI Voice was not turned on. No WhatsApp call was placed. Credentials were not written into source, git, tests, or this report.

## What Twilio returned

`GET /2010-04-01/Accounts/{AccountSid}.json` responded HTTP 401, error 20003. The message was that authentication failed because that account, status 4, is not active. The same 401 was returned for the incoming-number list and for `GET https://messaging.twilio.com/v2/Channels/Senders`.

Twilio’s own page for error 20003 says this response is used when credentials are wrong or when the account is suspended or closed: https://www.twilio.com/docs/api/errors/20003

Sender status, WABA link, messaging limit, voice capability, and WhatsApp Calling could not be read, because the account API would not authenticate. A phone number on an inactive account is not a usable WhatsApp sender. The Voice application and the public voice webhook were not created.

The production server was not opened. The OpenAI key on that server was not read. Realtime audio was not started.

WaSender’s number was not attached to this Twilio account.

## Final status

```
TWILIO CREDENTIALS:
CONFIGURED

TWILIO API:
REACHABLE

TWILIO ACCOUNT:
NOT ACTIVE

TWILIO WHATSAPP SENDER:
NOT ACTIVE

WHATSAPP BUSINESS CALLING:
NOT ENABLED

VOICE APPLICATION:
NOT CONFIGURED

PUBLIC WEBHOOK:
FAIL

WEBHOOK SIGNATURE:
FAIL

OPENAI REALTIME CREDENTIAL:
NOT CHECKED

REALTIME BRIDGE:
NOT READY

REAL WHATSAPP CALL:
NOT RUN

INBOUND AUDIO:
NOT RUN

OUTBOUND MAI AUDIO:
NOT RUN

MAI SPOKEN CONVERSATION:
NOT RUN

MULTI-TURN LIVE:
NOT RUN

ERP TOOL CALLING LIVE:
NOT RUN

EVENT REQUIREMENTS LIVE:
NOT RUN

BARGE-IN LIVE:
NOT RUN

CALLBACK LIVE:
NOT RUN

HANGUP LIVE:
NOT RUN

REAL CALL LATENCY:
NOT MEASURED

CALL RECORDING:
DISABLED

OUTBOUND WHATSAPP CALLING:
DISABLED

PSTN TRANSFER:
PROHIBITED

WASENDER PRODUCTION:
UNCHANGED

CUSTOMER VOICE:
DISABLED
```

The credentials were present for the API call. Twilio’s API host answered. The account is not active, so the sender, calling, voice application, and webhook were not configured. Signature validation was not run against a deployed voice URL. The OpenAI realtime credential was not confirmed on the server. The bridge is not ready. No call was made.

Stopped at this account blocker. Reactivating the Twilio account in the Twilio console is required before a sender, a voice application, or a test call can be configured.
