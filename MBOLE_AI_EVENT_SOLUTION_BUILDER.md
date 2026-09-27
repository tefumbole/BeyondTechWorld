# MBOLE AI — Event Solution Builder

Date: 27 September 2026  
Commit scope: BeyondTechWorld Laravel (`laravel-app`)

---

## 1. Current failure / root cause

**Symptom:** Customer says “I need speakers… wedding… Bamenda Congress Hall” and MAI answers about **BEHRINGER SINGLE BASS SPEAKER PASSIVE** being unavailable.

**Root cause (product-first path):**

1. `ServiceMenu::describe('sound')` listed raw catalogue product **names** (LIKE `%speaker%`), so Behringer appeared as a “choice”.
2. Slot extraction / OpenAI `check_rental_availability` used a vague query (`speaker` / `speakers`).
3. `RentalAvailabilityService::search($query, 1)` took the **first alphabetical** catalogue hit.
4. That single SKU was assessed and reported unavailable — instead of building an **event sound solution**.

Availability itself was already **qty-based** (not one-event-per-day). The failure was conversational + tool selection, not calendar exclusivity.

---

## 2. Product table fields used

From `products`:

| Field | Use |
|--------|-----|
| `id`, `name`, `code` | Identity / search |
| `category_id` | Existing ERP category (not replaced) |
| `qty` | On-hand stock for availability |
| `rent_price_per_day`, `price` | Catalogue / quotation unit pricing |
| `image` | Optional product card image |
| `is_active` | Active catalogue only |

Package commercial prices live in **`event_packages` / `event_pricing_rules`**, not hardcoded in prompts.

---

## 3. Package architecture

Tables (migration `2026_09_27_103000_create_event_packages_and_pricing.php`):

- `event_pricing_rules` — stage/m², truss, transport amounts
- `event_packages` — SOUND / LIGHTING / TRUSS / SOUND_MODE commercial offerings
- `event_package_components` — category/product requirements + search queries for inventory mapping
- `quotations.workflow_state` — sub-state without breaking integer `quotation_status`

Admin UI: `/admin/event-packages`

Seeded defaults:

| Category | Codes / amounts |
|----------|------------------|
| SOUND | BASIC 100k, STANDARD 200k, PREMIUM 300k |
| LIGHTING | BASIC 50k, STANDARD 150k, PREMIUM 300k, NONE 0 |
| TRUSS | WITHOUT_ROOF 300k, WITH_ROOF 500k, NONE 0 |
| SOUND_MODE | PLAYBACK, PLAYBACK_PIANO, FULL_LIVE |
| STAGE | 40,000 CFA / m² (`stage_per_m2`) |
| TRANSPORT | basic/standard 60k, premium 120k (within town) |

---

## 4. Product / category mapping

Components use `category_key` + `search_query` (and optional `product_id`) to resolve live `Product` rows. Alternatives are chosen when the first match is unavailable on the event date.

---

## 5–6. Event availability / multiple events

`RentalAvailabilityService` unchanged in principle:

`available = on_hand + returning − pending_overlap`

No “one event owns the day” lock. `EventSolutionBuilderService` explicitly returns `multiple_events_per_day_supported: true`.

---

## 7–13. Sound modes, packages, lighting, screen, stage, truss, transport

Deterministic services:

- `EventPackageCatalogService`
- `EventSolutionBuilderService`
- `StagePricingService` (4×4 → 16 m² → 640,000 CFA)
- `TrussPricingService`
- `TransportPricingService` (`TRANSPORT_QUOTE_REQUIRED` outside town)
- Screen: catalogue LED price or **Pending Pricing** (never invented)

---

## 14–15. Visual cards / structured UI

Tools may return `ui` (`OPTION_GROUP`, `QUOTATION_SUMMARY`, product lists).  
`WhatsAppConversationService::assistantReply` stores trusted `media_json`.  
Website widget continues to render **choices** (no AI HTML).

---

## 16. EventSolutionBuilderService

Path: requirements → packages → Product inventory → availability → commercial estimate → summary text.

---

## 17. OpenAI tools added

`get_sound_experience_options`, `get_sound_packages`, `get_lighting_packages`, `get_package_details`, `search_event_products`, `check_event_equipment_availability`, `calculate_stage_price`, `get_truss_options`, `calculate_transport_price`, `build_event_solution`, `calculate_event_estimate`, `create_event_quotation_draft`, `generate_quotation_review`, `get_quotation_review_status`

Vague `check_rental_availability` (`speakers`, `sound`, …) **redirects** to event-first (`event_first` + sound-mode UI) instead of Behringer.

---

## 18–20. Quotation / customer review / signature / admin approval

- Drafts still use Stage 4 `RentalQuoteService` / `quotations` integer statuses.
- `generate_quotation_review` → `STATUS_AWAITING` + secure approval URL.
- For `quotation_source` in `whatsapp|website`, client signature now sets **`workflow_state=customer_accepted`** and keeps **`STATUS_PENDING`** (pending admin) — **not** immediate `STATUS_APPROVED`.
- Legacy non-AI quotations keep previous approve → `STATUS_APPROVED` behaviour.
- `EventQuotationService::adminFinalApprove` sets final `STATUS_APPROVED`.

---

## 21. Branding

Customer-facing identity: **Mbole AI (MAI)**. System prompt updated. Internal class names unchanged.

---

## 22. Automated tests

`tests/Feature/EventSolutionBuilderTest.php` — **5/5 OK**

- Sound menu is event-first (no Behringer dump)
- Vague speaker availability redirects
- Stage math 4×4 / 6×4 / parse “4 by 4”
- Event solution summary includes venue + stage price
- `calculate_stage_price` tool

---

## 23. Live tests

| Test | Status |
|------|--------|
| Simple wedding not random Behringer | Ready after deploy — exercise website chat |
| All-in-one extraction | Via `build_event_solution` tool |
| Stage math | PASS (unit) |
| Multiple events same day | Architecture PASS (qty-based) |
| Alternative speakers | `search_event_products` / alternatives |
| Quotation + review + acceptance | Implemented; exercise with real customer phone |
| Revision | Partial — update draft via rebuild + new review (full revision UX can extend) |

---

## 24. Known limitations

1. Package commercial totals are stored in quotation **notes**; line items are physical products with catalogue unit prices (ERP line model unchanged).
2. Multi-day package multipliers escalate to pending pricing / staff review.
3. Outside-town transport always `TRANSPORT_QUOTE_REQUIRED` until a distance service exists.
4. Admin final-approve UI hook can be wired to quotation show page using `EventQuotationService::adminFinalApprove` (service ready).
5. Full 20-turn live wedding conversation should be smoke-tested on production after migrate + deploy.

---

## Files of note

- `app/Services/Event/*`
- `app/Services/Assistant/ServiceMenu.php` (event-first)
- `app/Services/Assistant/BeyondAssistantSystemPromptBuilder.php`
- `app/Services/Assistant/AssistantToolRegistry.php` / `Executor` / `Selector`
- `app/Http/Controllers/EventPackageAdminController.php`
- `app/Http/Controllers/QuotationApprovalController.php` (customer_accepted path)
- `database/migrations/2026_09_27_103000_create_event_packages_and_pricing.php`
- `MBOLE_AI_EVENT_SOLUTION_BUILDER.md` (this file)
