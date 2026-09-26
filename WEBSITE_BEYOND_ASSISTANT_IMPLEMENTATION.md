# Website Beyond Assistant (Mbole AI) — Implementation

## Architecture

Website visitors talk to the same **Beyond Assistant** stack used on WhatsApp. The website is only a transport/channel:

```
Visitor → Mbole AI widget → /api/website-chat/* → BeyondAssistantService
         → Assistant tools (rental ERP, quotes, handover, call request)
         → WhatsApp Hub inbox (channel=website)
```

| Concern | Implementation |
|--------|----------------|
| Session | `whatsapp_conversations.session_token` + `channel=website` |
| Anonymous identity | Contact `normalized_phone = web:{token}` until a real phone is shared |
| Inbound turn | Sync `BeyondAssistantService::handleIncoming` (no Wasender) |
| Outbound | Persist message with `provider_message_id=webmsg:…`; client polls |
| Leads | `LeadCatalog::SOURCE_WEBSITE` |
| Quotes | `quotation_source=website`; requires real phone (`need_phone` if still `web:`) |

## Routes (web middleware + CSRF + throttle)

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/api/website-chat/config` | Public widget config (no secrets) |
| POST | `/api/website-chat/session` | Create/resume session |
| GET | `/api/website-chat/messages` | Poll since cursor |
| POST | `/api/website-chat/messages` | Visitor message → assistant turn |
| POST | `/api/website-chat/minimize` | Persist minimize preference |

## Security

- No AI / Wasender keys in the browser — only session token + public endpoints.
- Rate limits: session 20/min/IP, messages 30/min/IP, daily AI turns per token/IP (`assistant.website_max_turns_per_day`).
- Max body length 2000 characters.
- Mode re-checked before assistant send; HUMAN mode → AI silence.
- Synthetic `web:` phones never go to Wasender.

## Settings (WhatsApp Hub → Settings)

- `website_ai_enabled`, `website_ai_auto_greeting`, `website_ai_greeting_delay_ms`
- `website_ai_name`, `website_ai_handover_enabled`, `website_ai_continue_whatsapp`
- Clarifications: existing `assistant_max_clarifications` / env `WEBSITE_AI_MAX_CLARIFICATIONS`

## Hub

- Inbox filters: channel All / WhatsApp / Website (+ filter “Website”).
- Conversation detail shows Channel label.
- Staff reply on website conversations stores outbound for poll (no provider send).

## Migration

`2026_09_27_003000_add_website_channel_to_whatsapp_conversations.php`

- `channel` (default `whatsapp`), `session_token` (unique nullable), `page_context`

## Tests

```bash
cd laravel-app && ./vendor/bin/phpunit --filter WebsiteChatTest
```

Covered: session create/resume, sync assistant reply, SOURCE_WEBSITE lead, no Wasender, HUMAN silence, staff poll reply, return to AI, rate limit, no duplicate conversation on refresh.

## Live checklist (after deploy — only when requested)

| # | Check | Pass? |
|---|--------|-------|
| 30 | FAB + greeting on public pages | |
| 31 | Open chat, multi-turn reply | |
| 32 | Refresh resumes same session | |
| 33 | Rental/ERP question uses tools (no invented prices) | |
| 34 | Ask for human → HUMAN + AI silent | |
| 35 | Staff reply appears in widget poll | |
| 36 | Return to AI resumes | |
| 37 | Mobile near-full-sheet layout | |
| 38 | Hub Website filter shows chat | |

## Primary files

- `app/Http/Controllers/WebsiteChatController.php`
- `app/Services/WebsiteChatService.php`
- `app/Services/WhatsApp/WhatsAppConversationService.php` (channel-aware send)
- `resources/views/beyond/partials/mbole_ai_widget.blade.php`
- `.cursor/rules/website-beyond-assistant.mdc`
- `tests/Feature/WebsiteChatTest.php`
