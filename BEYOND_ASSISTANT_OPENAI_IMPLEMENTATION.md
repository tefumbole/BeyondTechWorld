# Beyond Assistant — OpenAI Conversational Engine Implementation

## Problem / root cause

Casual follow-ups (e.g. “How are you today?”, “What’s up?”) were routed through intent classify → `UNKNOWN` @ confidence 0.2 → `AssistantPolicyService` **low_confidence → HUMAN**. Separately, ConversationalTurn provider failures always handed over. OpenAI was a secondary path after keyword routing. Full audit: [`BEYOND_ASSISTANT_OPENAI_ARCHITECTURE_AUDIT.md`](BEYOND_ASSISTANT_OPENAI_ARCHITECTURE_AUDIT.md).

## OpenAI integration

- Provider: [`OpenAiProvider`](laravel-app/app/Services/Assistant/Providers/OpenAiProvider.php) via curl Chat Completions (PHP 7.4 / Laravel 6; no SDK upgrade).
- Interface: [`AiProviderInterface`](laravel-app/app/Contracts/Ai/AiProviderInterface.php) returns `tool_calls`, usage, `latency_ms`.
- Shared by WhatsApp + Website through [`BeyondAssistantService`](laravel-app/app/Services/Assistant/BeyondAssistantService.php).

## Model configuration

ENV (preferred) with legacy `AI_*` fallbacks:

- `OPENAI_API_KEY`, `OPENAI_MODEL`, `OPENAI_ENABLED`
- `OPENAI_TIMEOUT`, `OPENAI_MAX_OUTPUT_TOKENS`, `OPENAI_TEMPERATURE`
- `OPENAI_WEB_SEARCH_ENABLED=false` (stub; not used in v1)

Settings UI shows only **OpenAI: Configured / Not Configured**.

## New routing architecture

1. Owner commands  
2. Explicit human / call request (deterministic)  
3. Greeting (deterministic; preserves name-capture memory)  
4. Operational intents (attendance, OTP, documents, internship ops, bills, maintenance)  
5. Service menu / closing / appointment drafts  
6. **OpenAI conversational engine** (primary for everything else)  
7. Soft clarify — never UNKNOWN→HUMAN  

## System prompt

[`BeyondAssistantSystemPromptBuilder`](laravel-app/app/Services/Assistant/BeyondAssistantSystemPromptBuilder.php) — Mbole AI identity, ERP tool discipline, handover policy.

## Conversation history + memory

- Recent messages via history limit + structured `assistant_memories` parameters (event/goal/equipment/handover_state, etc.).
- No chain-of-thought storage.

## Tool calling + registry

- [`AssistantToolSelector`](laravel-app/app/Services/Assistant/AssistantToolSelector.php) exposes role/goal subsets (not the full registry every turn).
- [`ConversationalTurnService`](laravel-app/app/Services/Assistant/ConversationalTurnService.php) multi-iteration tool loop (`max_tool_iterations`).
- New tool `search_company_knowledge`; `request_human_handover` requires `reason_category` enum.
- Authorization still in `AssistantToolExecutor` / policy — LLM is not the auth layer.

## Handover

Only: explicit human/call, privileged/identity where required, clarification budget, provider hard failure (`TOOL_FAILURE`), or `request_human_handover` tool.  
**Removed:** low_confidence / UNKNOWN auto-HUMAN.  
Race discard unchanged in `assistantReply`.

## Diagnostics

Hub Diagnostics: OpenAI configured/enabled/model, last ok/fail, latency, tool failures + **Test OpenAI** button (`whatsapp.diagnostics.test_openai`).

## Tests

```bash
cd laravel-app && ./vendor/bin/phpunit --filter 'OpenAiConversationalEngineTest|WhatsAppConversationalTest|WebsiteChatTest'
```

Critical suite (`OpenAiConversationalEngineTest`): Hello → How are you today? → What’s up? → How is Beyond today? → **zero handovers**; unseen paraphrase; explicit human then AI silence; UNKNOWN policy ≠ HANDOVER.

## Live results

Not run on VPS in this pass (stop condition / no deploy unless requested).

## Known limitations

- Web search disabled.
- Native OpenAI `tool_calls` used when the API returns them; NullAiProvider tests use JSON `reply`/`tool` compatibility path.
- Greeting still uses deterministic composer for “Hi” (name memory); open-ended small talk uses OpenAI.
- Full Stage suite beyond conversational filters should be re-run before production cutover.
