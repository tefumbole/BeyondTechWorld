# WhatsApp Hub — Phase 3 Implementation Audit

Date: 22 September 2026  
Scope: Phase 1 foundation + Phase 2 conversations/leads only. Not a full ERP audit.

**Verdict: no serious architectural conflict. Phase 3 can proceed.**

---

## Conversation lifecycle

`WhatsAppConversationService` is the single owner.

1. Inbound webhook → persist event → `ProcessWasenderWebhook` (`whatsapp` queue) → `recordIncoming`.
2. Contact is found/created and identity links are synced.
3. One open conversation per contact is reused.
4. Inbound sets `status = WAITING_STAFF`, increments unread, runs Phase 2 lead capture.
5. Staff reply sets `WAITING_CUSTOMER` and sends through `WhatsAppProviderInterface` → `WaSenderProvider` → existing `BeyondWasenderService`.
6. Closed/resolved conversations reopen on a new inbound.

**Phase 3 hook:** after a new inbound message is stored, dispatch `ProcessAssistantTurn` only when policy allows. The webhook HTTP path stays receive → verify → persist → queue → 200.

---

## Modes (AI / HUMAN / PAUSED / CLOSED)

Already stored on `whatsapp_conversations.mode`. Phase 2 operations:

| Action | Mode | Status |
|---|---|---|
| Takeover | HUMAN | reopen if closed |
| Release | HUMAN | unassigned |
| Pause | PAUSED | unchanged |
| Close | CLOSED | CLOSED |
| Reopen | HUMAN | OPEN |

**Conflict to fix in Phase 3 (not a blocker):** `openConversation()` currently forces HUMAN when the default mode is AI (Phase 1/2 safety). Phase 3 must allow AI when the global assistant switch is on. Closed inbound currently reopens to HUMAN, not the configured default.

Default mode is `whatsapp_settings.default_conversation_mode` (env `WHATSAPP_DEFAULT_CONVERSATION_MODE`, currently HUMAN).

---

## Leads

Separate CRM tables (`leads`, `lead_activities`), not About-Us Leaders. Unknown meaningful enquiries auto-create a lead. Known ERP identities do not. Staff can create a lead manually. Convert links or creates a `customers` row. Assistant may read `get_current_lead` only.

---

## Assignment / handover

Phase 2 already has assign / takeover / release / pause / close / reopen + `whatsapp_conversation_events`. Phase 3 human handover must call this stack and set mode HUMAN. AI must stay silent after takeover.

---

## Identity

`WhatsAppIdentityService::resolve()` returns every matching role: user, employee, customer, supplier, applicant, intern. Links are polymorphic. **Identity ≠ authorization** — Phase 3 policy must treat employee/intern/customer separately.

---

## Context

`WhatsAppContextService` already builds a minimum customer / intern / employee / lead / SLA block for Hub staff. Assistant context must stay smaller and never dump Eloquent models into prompts.

---

## Outbound provider

Unchanged. Assistant replies must use `WhatsAppProviderInterface` with `sender_type = ASSISTANT`. Do not call Wasender from the assistant layer.

---

## Queue

`QUEUE_CONNECTION=database` in production. Worker: `beyondtechworld-whatsapp-queue` (`whatsapp,default`). AI turns must be another job on `whatsapp`, never inside the webhook HTTP request.

---

## Permissions

Phase 1/2: `whatsapp.*` for roles 1–2. Phase 3 adds `whatsapp.ai`, `.ai.manage`, `.ai.knowledge`, `.ai.tools`, `.ai.activity`, `.ai.suggest`. Ordinary staff do not get AI admin.

---

## Audit already present

Webhook events, conversation events, lead activities, internal notes. Phase 3 adds `assistant_activities` (operational metadata only — no chain-of-thought).

---

## ERP services to reuse (read-only in Phase 3)

| Domain | Reuse | Do not rebuild |
|---|---|---|
| Customer | `Customer` + identity links | new CRM |
| Booking/Rental | `Booking`, `BookingProduct` | availability/pricing engine (Phase 4) |
| Products | `Product` (`is_active`, rent prices as **catalog** only) | date availability claims |
| Quotation | `Quotation::statusLabel()` | new quote writer |
| Payments | `Sale` paid/grand_total | confirm payment from customer text |
| Internship | `InternshipEnrolment`, `InternshipTaskAssignment`, `InternshipProgramService` | WhatsApp submission/grading (Phase 5) |
| Employee | `Employee` | privileged HR disclosure |
| Documents | Phase 2 path-jailed send | OTP document workflow (Phase 7) |
| Company | `GeneralSetting` / `COMPANY_NAME` + assistant knowledge rows | giant system prompt |

No dedicated Booking/Quotation/Payment application services exist — tools must query the existing models the same way controllers do.

---

## Safety constraints for Phase 3

- No LLM SQL, credentials, or arbitrary model access.
- Sensitive finance (`BALANCE_ENQUIRY`, receipts, contracts) = VERIFIED → do not auto-disclose (no OTP yet).
- “I paid 500,000” is not ERP confirmation.
- Customer-stated prices are not ERP prices.
- Global **Disable Beyond Assistant** must stop AI sends without breaking Hub.
- Secrets only in `.env` / `config/assistant.php`. Hub UI shows Configured/Missing only.

---

## Proceed

Phase 3 implements Beyond Assistant as a queued, tool-gated layer on top of the existing Hub. Phase 4 rental automation is out of scope.
