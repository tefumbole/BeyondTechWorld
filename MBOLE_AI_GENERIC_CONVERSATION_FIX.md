# MBOLE AI — Generic Conversation Fix

Date: 27 September 2026  
Scope: BeyondTechWorld Laravel app (`laravel-app`) — website chat + WhatsApp Beyond Assistant  
Status: **Architecture fixed in code.** Live OpenAI answers remain blocked until production `OPENAI_API_KEY` / `AI_API_KEY` is set (verified missing).

---

## 1. Exact root cause

Two stacked failures:

1. **Production had no OpenAI API key** (`CONFIG_API_KEY_SET=NO`, `ENV_AI_API_KEY_SET=NO`, `ENV_OPENAI_API_KEY_SET=NO`, DB `openai_api_key`/`ai_api_key` empty). App bound **`NullAiProvider`**.
2. **`NullAiProvider::isConfigured()` returned `true`** even with empty scripts, so the conversational engine called `complete()` which returned empty `{}`.
3. **`ConversationalTurnService`** treated empty model output as a soft clarify and returned the hard-coded business menu:

   > Could you tell me a bit more about what you need — equipment rental, training/internship, or something else?

**OpenAI was never called** for the failing line-array question.

Introduced in commit `8bb8ac8` (“Make OpenAI the primary Beyond Assistant conversational engine.”), file `app/Services/Assistant/ConversationalTurnService.php` ~line 205 (historical).

---

## 2. File / method that generated the bad clarification

| Layer | Exact location |
|--------|----------------|
| Soft-clarify string | `ConversationalTurnService::turn()` empty-output branch (removed) |
| Bound provider | `AppServiceProvider` → `NullAiProvider` when no key |
| Website path | `WebsiteChatController` → `WebsiteChatService::visitorMessage` → `BeyondAssistantService::handleIncoming` → `ConversationalTurnService::turn` |

Frontend (`mbole_ai_widget.blade.php`) only displays the backend `body`. No business-menu fallback in JS.

---

## 3. Was OpenAI called for the failed screenshot?

**NO.**

Evidence (production check before this fix was fully deployed):

- Bound provider: `null`
- `NullAiProvider::isConfigured()`: `YES` (old buggy behaviour)
- Key configured: `NO`
- Soft-clarify string sourced only from `ConversationalTurnService` (git history)

---

## 4. Model used (failed turn)

**None / `null`.** Configured target model was `gpt-4o-mini` but OpenAI HTTP was never invoked.

---

## 5. tool_choice used (failed turn)

N/A for Null path. Production engine now uses **`tool_choice = auto`** for conversational turns (`ConversationalTurnService` + `OpenAiProvider`).

---

## 6. Routing changes

Correct default after protected workflows:

```
Incoming → human ownership? → security/deterministic ops? → pending OTP/location? → known deterministic command?
  → else OPENAI ConversationalTurnService (tool_choice=auto)
       → answer direct | tool call | natural clarify
```

Changes:

- Removed business-menu soft clarify from `ConversationalTurnService`.
- Empty / failed provider → **`FALLBACK_ERROR`** (honest message), **not** rental/internship menu, **not** forced HUMAN handover.
- `BeyondAssistantService` last-resort UNKNOWN no longer sets “needs clarification” business menu.
- `NullAiProvider::isConfigured()` is `true` **only when scripted** (tests); empty Null never pretends to be OpenAI.
- `AssistantAiConfig` centralises key detection (`OPENAI_API_KEY` / `AI_API_KEY` / settings).
- Intent classification may still run for analytics/last resort; it **must not** block OpenAI for general questions (primary path is conversational before last-resort classify).

---

## 7. Prompt changes

`BeyondAssistantSystemPromptBuilder` now mandates:

- General conversational assistant (not business-category gated)
- Direct answers for ordinary / educational / technical questions
- No “rental or internship?” when the question is already clear
- ERP tools only for Beyond-specific / user-specific authoritative data
- No handover merely for unmatched intent
- `tool_choice` auto semantics described in prompt

---

## 8. Tool-description changes

Updated in `AssistantToolRegistry` (examples):

- `search_rental_products` — inventory only; **not** general education
- `check_rental_availability` — stock/dates; **not** AV theory
- `get_current_internship_task` — person’s task; **not** “what is a VLAN?”
- `get_internship_summary` / related — person-specific; not general internship education

---

## 9. Frontend changes

None required for routing. Widget already:

`send message → backend → display returned assistant message`

No hardcoded business fallback text found in `mbole_ai_widget.blade.php` (only network-error UI).

---

## 10. Production configuration verification

Checked on VPS (`/var/www/beyondtechworld/laravel-app`) **without printing secrets**:

| Check | Result |
|--------|--------|
| `OPENAI_ENABLED` / `assistant.enabled` | **YES** |
| Config `assistant.api_key` set | **NO** |
| Env `AI_API_KEY` set | **NO** |
| Env `OPENAI_API_KEY` set | **NO** |
| DB `openai_api_key` / `ai_api_key` | **NO** |
| Model | `gpt-4o-mini` |
| Provider setting | `openai` |
| Bound provider (pre-deploy) | `null` |
| Config cache file | **NO** |
| Assistant hub switch | enabled |

**Live OpenAI direct answers cannot succeed until a valid key is configured** in production `.env` (or WhatsApp settings) and PHP-FPM/queue workers are restarted. Then re-check Diagnostics → “OpenAI configured: YES” and “Bound provider: openai”.

---

## 11. Direct-answer test results

### Automated (local PHPUnit)

`OpenAiConversationalEngineTest` + related suites: **29 tests OK** including:

- General AV question → `OPENAI_DIRECT`, no business menu
- Missing key → `FALLBACK_ERROR`, no business menu
- Provider fail → `FALLBACK_ERROR`, stays AI mode

### Admin diagnostic

- Artisan: `php artisan assistant:direct-test --battery --with-tools`
- Hub: `/admin/whatsapp/diagnostics` → “Test OpenAI direct answer”
- Expected when key present: `response_source=OPENAI_DIRECT` for line-array / VLAN / greeting battery

### Live battery (requires key)

| # | Question | Expected |
|---|----------|----------|
| A | How are you today? | OPENAI_DIRECT |
| B | What is a line array? | OPENAI_DIRECT |
| C | Difference line array vs point source | OPENAI_DIRECT |
| D | What is a VLAN? | OPENAI_DIRECT |
| E | Why are subwoofers used at concerts? | OPENAI_DIRECT |

**Status:** Code ready; **blocked on production key** for live OpenAI runs.

---

## 12. ERP-transition test results

Not executed live (key missing). Expected after key:

| Turn | Expected |
|------|----------|
| Which line arrays does BeyondTechWorld have? | OPENAI_TOOL_ASSISTED → inventory |
| How much are they? | OPENAI_TOOL_ASSISTED → pricing (context) |
| Can I get six on Saturday? | OPENAI_TOOL_ASSISTED → availability |

---

## 13. Internship test results

Not executed live (auth + key). Expected:

| Message | Expected |
|---------|----------|
| What is a VLAN? | OPENAI_DIRECT |
| What am I supposed to do today? | OPENAI_TOOL_ASSISTED → current task |
| I don’t understand the task | OpenAI explains from prior tool context |
| Did my supervisor approve yesterday? | OPENAI_TOOL_ASSISTED → status/submission |

---

## 14. Twenty unseen-question results

Deferred until production key is set. Protocol:

1. Invent 20 new general questions (not in this doc; not keyword-listed).
2. Score ≥18/20 as reasonable direct answers without business-menu / incorrect handover.
3. Record in this file when run.

Placeholder table:

| # | Question (invented at test time) | Source | Pass? |
|---|----------------------------------|--------|-------|
| 1–20 | _TBD after key_ | | |

---

## 15. Live website results

**Pre-fix live failure (proven):**

User: “what is the difference between line array and point source speaker”  
AI: business-menu soft clarify from `ConversationalTurnService` via NullAi.

**Post-architecture (without key):** visitors get `FALLBACK_ERROR` safe text, **not** the rental/internship menu.

**Post-key (required for success criterion §23):** website must produce natural greeting → line-array explanation → contextual follow-ups → ERP tools for Beyond-specific inventory/pricing/availability with **zero** business-menu fallbacks.

---

## 16. Remaining limitations

1. **Production OpenAI API key is not configured** — primary blocker for live success criterion.
2. Queue workers / PHP-FPM must see the same env after key is added (`config:clear` if config ever cached).
3. Greeting / service-menu / attendance / OTP remain deterministic by design (correct).
4. Unseen 20-question battery and live ERP/internship chains still need to be recorded after key + deploy.
5. Do not add keyword lists for “line array” etc. — semantic OpenAI path is the fix.

---

## Execution path (evidence)

```
Website widget message
  → POST /api/website-chat/messages
  → WebsiteChatController
  → WebsiteChatService::visitorMessage
  → BeyondAssistantService::handleIncoming
  → ConversationalTurnService::turn
       [FAILING LIVE PATH]
       → NullAiProvider (no key) → empty "{}"
       → soft business-menu clarify  ← REMOVED
       [FIXED PATH]
       → if !isConfigured → FALLBACK_ERROR (honest)
       → if OpenAI configured → complete(..., tool_choice=auto)
            → OPENAI_DIRECT | OPENAI_TOOL_ASSISTED | CLARIFICATION | HUMAN_HANDOVER
  → assistantReply body returned verbatim to widget
```

---

## Diagnostics added

Response sources: `OPENAI_DIRECT`, `OPENAI_TOOL_ASSISTED`, `ERP_DETERMINISTIC` (via activity tools), `SECURE_WORKFLOW` (deterministic ops), `CLARIFICATION`, `HUMAN_HANDOVER`, `FALLBACK_ERROR`.

Cached: `assistant_last_turn_diag`, `assistant_openai_last_ok/fail`, `assistant_direct_test_last`.

Commands / UI: `php artisan assistant:direct-test`, WhatsApp Hub Diagnostics direct-answer form.
