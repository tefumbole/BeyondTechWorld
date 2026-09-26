# Beyond Assistant — OpenAI Architecture Audit

## 1. Current inbound flow

```
WhatsApp webhook / Website chat POST
  → WhatsAppConversationService::recordIncoming (WA) or WebsiteChatService::visitorMessage (web)
  → BeyondAssistantService::handleIncoming
  → mayProcess (mode must be AI)
  → AssistantIntentRouter::deterministic (regex keywords)
  → If low confidence / miss → ConversationalTurnService (JSON OpenAI turn)
  → Else / fallback → classify() → UNKNOWN@0.2
  → AssistantPolicyService::decide
  → ANSWER | TOOL | CLARIFY | HANDOVER
  → WhatsAppConversationService::assistantReply (channel-aware; website skips Wasender)
```

Website and WhatsApp already share `BeyondAssistantService` (do not duplicate).

## 2. Current AI provider

- Interface: `App\Contracts\Ai\AiProviderInterface`
- Binding: `AppServiceProvider` → `OpenAiProvider` if `assistant.api_key` set, else `NullAiProvider`
- Transport: curl POST to Chat Completions (`assistant.api_url`)

## 3. Current model

- Config: `config/assistant.php`
- Defaults: `AI_MODEL` / `gpt-4o-mini`, key `AI_API_KEY`, enable `WHATSAPP_ASSISTANT_ENABLED`
- JSON `response_format` forced; **no native tool_calls** yet — tools requested via JSON fields in content

## 4. Current intent classifier

- `AssistantIntentRouter::deterministic` — large regex catalog (attendance, OTP, rental, greeting, human request, …)
- `classify()` — deterministic if confidence ≥ `confidence_high` (0.75), else LLM classify, else `UNKNOWN` @ 0.2
- Greeting regex includes `how are you`, but many casual paraphrases do not

## 5. Current tool registry

- `AssistantToolRegistry` — ~70 Stage 3–9 tools (rental, quotation, internship, attendance, documents, property, owner)
- `ConversationalTurnService` only exposes **8 read** rental/company tools; disallowed tool → handover

## 6. Current memory

- Table `assistant_memories` via `AssistantConversationMemory`
- JSON parameters: event slots, awaiting_name, clarification_count, last tools
- History window: last N WhatsApp messages (`assistant_history_limit`)

## 7. Current handover triggers

| Trigger | Location | Reason |
|--------|----------|--------|
| `confidence < 0.40` and not GREETING | `AssistantPolicyService::decide` L68–69 | `low_confidence` |
| UNKNOWN @ 0.2 from classify | IntentRouter → policy | same |
| HUMAN_REQUEST / COMPLAINT / CALL_REQUEST | policy | customer_request / call_request |
| PRIVILEGED / identity missing | policy | privileged / identity_required |
| Clarification count exceeded | BeyondAssistantService | too_many_clarifications |
| ConversationalTurn AI fail / not configured | `ConversationalTurnService::fallback` | ai_provider_* → **handover true** |
| Disallowed conversational tool | ConversationalTurnService | privileged |
| Explicit human regex (includes broad `i need help`) | IntentRouter | HUMAN_REQUEST |

## 8. Why casual conversation fails

**Root cause:** OpenAI is a secondary path. When a phrase misses keyword GREETING (or ConversationalTurn fails / is skipped), classify returns `UNKNOWN` @ 0.2 → policy maps **low confidence → HUMAN**. Separately, any AI provider failure in ConversationalTurn **always handovers**. Casual chat is treated like “no intent” instead of “let the model talk.”

`Hello` matches GREETING → ANSWER. `What's up?` / many paraphrases of wellbeing / company small-talk do not → HUMAN.

## 9. Components that can be reused

- WaSender + Hub + ERP modules (Stages 3–7+)
- `AssistantToolRegistry` / `AssistantToolExecutor` / authorization in policy
- `AssistantHandoverService`, race discard in `assistantReply`
- `WebsiteChatService` → same BeyondAssistantService
- `OpenAiProvider` curl client, `assistant_knowledge`, memory table
- Deterministic regexes for ops (check-in, OTP, docs) — keep as gate, not general fallback

## 10. Proposed changes

1. Invert routing: deterministic **ops only** → else OpenAI conversational engine
2. UNKNOWN / low confidence → **never** auto-HUMAN; clarify or answer via OpenAI
3. Native tool_calls loop + `AssistantToolSelector` (subset by role/goal)
4. Central `BeyondAssistantSystemPromptBuilder` (Mbole AI)
5. Handover only via explicit request / policy / `request_human_handover` tool categories
6. OPENAI_* env (legacy AI_* fallback); diagnostics Configured badge + Test OpenAI
7. Provider hard failure ≠ “I didn’t understand” (separate paths)

See `BEYOND_ASSISTANT_OPENAI_IMPLEMENTATION.md` after code lands.
