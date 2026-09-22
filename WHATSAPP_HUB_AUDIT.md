# BeyondTechWorld WhatsApp Hub — Phase 0 Audit

**Company:** BeyondTechWorld  
**Website:** beyondtechworld.com  
**Target application:** live Laravel ERP (`laravel-app/`, Laravel 6 / PHP 7.4, Blade + jQuery)  
**Audit date:** 22 September 2026  
**Status:** Phase 0 complete. No Hub implementation until this document is reviewed and Phase 1 is explicitly approved.

This audit inspects the existing BeyondTechWorld ERP before any WhatsApp Operations Hub work. The goal is to extend the current WaSender outbound stack into an inbound + operational interface **without** duplicating ERP modules or replacing working notifications.

**Out of scope**

- Sibling React repo “Beyond Tech”
- Leftover `BeyondTechWorld/src` (React) and `BeyondTechWorld/apps/api` (Node)
- Isolated public pages that must stay off SiteMenu and untouched: `/pangwayu/remember`, `/mambole`

**Architectural rule**

```
WaSender  →  WhatsApp Hub  →  Beyond Assistant  →  existing ERP services  →  existing tables
```

WhatsApp is an interface. It must not become a second ERP.

```
WhatsApp user
      │
      ▼
WaSenderAPI
      │
      ├── outbound (EXISTS) ── NotificationRouter ── BeyondWasenderService ── ERP modules
      │
      └── inbound (MISSING) ── webhook ── Hub ── Beyond Assistant ── ERP services
```

---

## 1. Stack and application shape

| Item | Finding |
|------|---------|
| Framework | Laravel 6, PHP ^7.2 (production PHP 7.4) |
| UI | Blade + Bootstrap + jQuery. Vue is scaffold-only (`ExampleComponent.vue`). No Vite admin. |
| Routes | Almost everything in `laravel-app/routes/web.php`. `routes/api.php` is a stub (`auth:api` `/user` only). |
| Auth | ERP `users` + Spatie Permission. Separate portal `be_users` / `BeyondAuthService` for public/staff OTP. |
| Admin shell | `resources/views/layout/main.blade.php` + `app/Support/SiteMenu.php` |
| Public site | `resources/views/beyond/` |
| PDF | `barryvdh/laravel-dompdf` — no Snappy |
| Queue | Jobs exist; `QUEUE_CONNECTION` defaults to **sync** |
| Broadcast | Pusher env present; `BROADCAST_DRIVER` default `null`; unused for messaging |
| Tests | Only Laravel example PHPUnit tests |

Admin ERP group uses `auth`, `active`, `intern.compliance`. Web requests are logged by `LogActivity`.

---

## 2. Existing WaSender / WhatsApp implementation

The Laravel app is a **production outbound messaging hub**. It is **not** an inbound WhatsApp operations platform.

### 2.1 Core send stack (keep)

| Path | Role |
|------|------|
| `app/Services/BeyondWasenderService.php` | Wasender client: `sendTextRaw`, `sendDocument`, `sendImage`, `getContactName`, upload. File lock `storage/app/whatsapp-send.lock`. Interval ≥ 5.5s (honours `WASENDER_MIN_SEND_INTERVAL_MS`, typically 6000). Stamp **after** send completes. One retry on account-protection errors. Deployed `e006b7a`. |
| `app/Services/Messaging/NotificationRouter.php` | Public facade: `sendWhatsAppText`, `sendWhatsAppTextWithLink`, `sendWhatsAppOtp`, `sendWhatsAppDocument`, announcements, admission. Provider `WHATSAPP_SERVICE` = `WASENDER` \| `TWILIO`. Documents always Wasender. |
| `app/Services/TwilioWhatsAppService.php` | Optional Content Template sends. |
| `app/Services/WhatsAppService.php` | Legacy facade → router. |
| `app/Http/Controllers/Controller.php` | Older `wpMessage` / `wasenderAttachment` / UltraMsg leftovers. Still used by some sales/booking flows. **Do not add a third send path.** |
| `app/Support/WhatsAppPhone.php` | Canonical normalizer: storage `237…`, send `+237…`. |
| `app/Traits/NormalizesWhatsAppPhones.php` | Auto-sanitize on model save. |
| `app/Support/WhatsAppMessage.php` | Large static template library (internship, bookings, quotations, OTP, letters, shareholders, funeral). |
| `app/Console/Commands/WhatsAppStatus.php` | `whatsapp:status` — Wasender `GET /status` and `/whatsapp-sessions/{id}`. |
| Settings → Messaging | Writes Wasender/Twilio env via `SettingController` + `EnvFile`. |

### 2.2 Wasender API surface already used (outbound only)

- POST `{base}/send-message` — text, `documentUrl`, `imageUrl` (also video/audio via legacy Controller)
- POST `{base}/upload` — public media URL
- GET `{base}/contacts/{digits}` — display name
- GET `/status`, `/whatsapp-sessions/{id}` — CLI session health only

### 2.3 Inbound / webhooks

**None.** No Wasender or Twilio inbound routes. CSRF exceptions (`logout`, `portal/logout`, `pangwayu/eulogy`, `mambole/submit`) do not include a messaging webhook.

Current WaSender events (from official docs; **not implemented here**):

| Event | Purpose |
|-------|---------|
| `messages.received` | Incoming personal/group message + media |
| `messages.update` | Status 0 ERROR … 4 READ, 5 PLAYED |
| `message-receipt.update` | Per-recipient group receipts |
| `call` | Incoming voice/video — **tracking only**, not AI voice answering |

Verify header: `X-Webhook-Signature`. Return HTTP 200 quickly; process asynchronously.

### 2.4 Tracking tables that already exist

| Store | What it tracks | Gap |
|-------|----------------|-----|
| `internship_notification_logs` | Idempotent internship WhatsApp (`idempotency_key`, `provider_message_id`, `status`) | Module-specific; no delivered/read |
| `message_delivery_batches` / `message_delivery_items` | Letter / invitation bulk send queued→sent/failed | No read/delivered |
| `wa_announcements.send_results_json` | Announcement per-recipient results | JSON blob |
| `task_assignments.whatsapp_sent` | Boolean attempted | Not provider ACK |
| `internship_task_assignments.whatsapp_sent_at` / `whatsapp_message_id` | Last task send | No lifecycle |

Wasender `msgId` is sometimes stored. Nothing consumes delivery/read webhooks.

### 2.5 Major outbound consumers (do not break)

Internship (`InternshipProgramService`), announcements, letters, tasks, bookings/rental reminders, quotations, sales/delivery, contracts, training, shareholders, applications, birthday flyer, funeral pledges, OTP login (`PhoneOtpLoginController`, `StaffPhoneAuthController`, `BeyondAuthService`).

Internship already sends: daily task, Word handbook, supervisor copy, submission received, pass/grade, SLA auto-accept, timesheet reminder, working-week request. Auto-grade now sends the **pass message first**, then next-task WhatsApp, through the send lock.

### 2.6 Env already present

`MESSAGING_WHATSAPP_ENABLED`, `WHATSAPP_SERVICE`, `WHATSAPP_DEFAULT_COUNTRY_CODE`, `WASENDER_API_KEY`, `WASENDER_SESSION_ID`, `WASENDER_BASE_URL`, `WASENDER_MIN_SEND_INTERVAL_MS`, `WASENDER_TEXT_TO_DOCUMENT_DELAY_MS`, Twilio Content SIDs, UltraMsg leftovers.

**Not present:** `WASENDER_WEBHOOK_SECRET`, `WHATSAPP_ASSISTANT_ENABLED`, Google Calendar, AI keys.

---

## 3. Identity and people

There is **no** WhatsApp identity table. Phones already live on ERP records.

| Entity | Phone fields | Notes |
|--------|--------------|-------|
| `User` | `phone`, `additional_phone` | Interns, staff, portal users |
| `Customer` | `phone_number` | LIMS customer |
| `Employee` | `phone_number` + `user_id` | HRM employee |
| `Supplier` | `phone_number` | |
| `Application` | `phone`, `whatsapp_number` | Job applicants |
| Internship student | via `users` on enrolment | |
| Also | shareholders, training registrations, funeral, birthday | |

`PeopleDirectoryService` searches several of these for Task Manager (prefixed IDs `user:`, `customer:`, `applicant:`). It is a **lookup**, not a multi-role resolver.

**Rule for Hub:** one number may be employee **and** customer **and** intern. Use polymorphic `whatsapp_contact_links`, not a single `customer_id` column.

---

## 4. ERP modules vs Hub domains

### 4.1 Customers

Exists: `Customer`, groups, admin CRUD, WhatsApp-normalized phone.  
Missing: CRM pipeline, activities, lead scoring. Customer is POS/rental, not a full CRM contact.

### 4.2 Leads / CRM

**Missing.** `Leader` is About-Us people, not a sales lead. Unknown WhatsApp numbers have nowhere to land except a future `leads` table (Phase 2).

### 4.3 Quotations

Strong and must be reused.

- Models: `Quotation`, `QuotationQuote`, `QuotationQuoteLine`
- PDF + client approval token + convert to sale
- Statuses (do **not** rename): Draft (1), Awaiting Client Signature (2), Approved (3), Rejected (4), No Signature (5), Client Quote (6)

Hub language (DRAFT / SENT / ACCEPTED / …) must **map** onto these integers.

### 4.4 Rentals / inventory

`Product` + warehouse price + `Booking` / `BookingProduct` / `BookingContract` / goods received / `rental:return-reminders`.  
Booking statuses: Draft, Pending, Completed, Return, Partial Return.  
Public `/rentals` + token sign.  
No separate WhatsApp price list. Availability = inventory + conflicting bookings (must be computed from ERP, never invented by AI).

### 4.5 Events / production

`btw_events` with workforce, contracts, payments, publications. Admin calendar view is a **stub**. Not Google Calendar.

### 4.6 Internship / students

Full module: programs, tasks, enrolments, assignments, submissions, files, grades, rubric, handbooks, supervisor chat (web form → outbound WhatsApp, not inbound inbox).  
Courses (`courses`, registrations, student portal) are a **separate** training product. Do not hardcode tracks; use `InternshipProgram` rows.

WhatsApp submission via inbound media is **missing**. Grading stays in existing supervisor UI. AI must not grade official work.

### 4.7 Employees / attendance / timesheet / payroll

- LIMS `Attendance`: `date`, `employee_id`, `checkin`, `checkout`, `status`, `note` — **admin-entered**, not self-service.
- Beyond `be_timesheet_*` + `WorkingWeek` + intern compliance.
- `Payroll`, holidays, job board, letters, event crew timesheets.

WhatsApp check-in (Phase 6) must write these existing tables, not a parallel attendance ledger.

### 4.8 Finance / payments

`Sale`, `Payment`, POS, purchases, accounts, booking payments, course invoices, wealth manager.  
“Receipt” = PDF of sale/booking/payment, not a unified receipt entity.  
Campay/Pawapay webhook **secrets** exist in config; not WhatsApp.  
No bill-payment-request workflow.

### 4.9 Contracts / documents / PDF

dompdf templates under `resources/views/pdf/` (sales, bookings, quotations, letters, signed rental/accommodation/studio, event contracts).  
`BtwContract` workflow: draft → review → send → sign.  
No document OTP gate. No DMS.

### 4.10 Tenants / property

**Not implemented.** Accommodation language exists on booking/contract PDFs only. No `Property`, `Unit`, `Lease`, or maintenance ticket. Phase 8 needs a property module first, or tenant cards must be omitted.

### 4.11 Appointments / Google Calendar

**Missing.** No `google/apiclient`. Event calendar is internal and incomplete. Phase 9 is greenfield.

### 4.12 AI / OpenAI

**None** in runtime PHP. Mentions only in internship/training curriculum text.

### 4.13 Queues / scheduler

`app/Console/Kernel.php` already runs internship reconcile/SLA/timesheets, rental returns, booking reminders, announcements, letters, tasks, invitations, contracts, wealth sync.

Jobs (`ProcessQueue`, `SendAnnouncementJob`, `SendOnlineInvitationJob`) implement `ShouldQueue` but production typically runs **sync**. Letters/birthday already use persist + `fastcgi_finish_request`.

### 4.14 Audit

`activity_logs` + HTTP `LogActivity` + contract audit + `internship_activity_logs`. Suitable to extend; Hub should add a focused WhatsApp action log later without dumping secrets.

### 4.15 Dashboard / UI reuse

`SiteMenu::sideItems()` is the canonical admin nav (Dashboard, Product, Sale, Rental Module, Events, Tasks, Jobs, Contracts, Announcements, Courses, TimeSheets, Quotation, HRM, People, …).  
Sidebar in `layout/main.blade.php` is permission-gated collapse sections (copy the Announcements pattern).  
Reusable look: Bootstrap cards, tables, badges, modals. No React Hub.

### 4.16 RBAC

Dominant check: `Role::find(Auth::user()->role_id)->hasPermissionTo('…')`.  
Module seed pattern (announcements / internship):

- `whatsapp_module`
- dotted names (`whatsapp.view`, `whatsapp.conversations`, …)
- `Permission::firstOrCreate` + grant roles **1 and 2**

Do not create a separate auth system. `AuthServiceProvider` has no custom gates.

---

## 5. A. Existing functionality we can reuse

- `BeyondWasenderService` + send lock + retry
- `NotificationRouter` (provider switch)
- `WhatsAppPhone` / `NormalizesWhatsAppPhones`
- `WhatsAppMessage` templates
- `whatsapp:status` session probe
- Settings → Messaging env UI
- Spatie permission seed + sidebar pattern
- `SiteMenu` + `layout/main`
- `PeopleDirectoryService` multi-table phone search (as a pattern)
- Internship program/task/submission/grade + existing outbound WhatsApp
- Bookings, products, quotations, sales, payments, contracts, PDFs
- `Attendance` + timesheets + payroll
- `internship_notification_logs` idempotency
- `activity_logs`
- Existing cron commands
- `routes/api.php` as webhook home (no CSRF)

---

## 6. B. Existing functionality requiring modification

| Area | Change (when Phase 1+ starts) |
|------|-------------------------------|
| Send stack | Wrap with `WhatsAppProviderInterface` / `WaSenderProvider` that **delegates** to `BeyondWasenderService`. Do not rewrite HTTP. |
| Outbound logging | Later attach `msgId` into Hub `whatsapp_messages` without changing send behaviour. |
| `SiteMenu` + sidebar | Add WhatsApp Hub item (Phase 1). |
| `config/services.php` + `.env.example` | Add `WASENDER_WEBHOOK_SECRET` only. Reuse existing keys. |
| Quotation / booking statuses | Map Hub labels onto existing integers. Do not rename columns. |
| Future check-in | Write `attendances` / timesheets; do not create a second ledger. |
| Queue | Prefer `database` driver for webhook jobs; if production stays `sync`, persist then `fastcgi_finish_request`. |
| Legacy `Controller` send helpers | Leave working. New code must not call them. |

---

## 7. C. Missing functionality

- Inbound webhook endpoint, signature verification, idempotent event store
- Contacts / multi-role identity resolver
- Conversations, inbox, staff reply, AI / HUMAN / PAUSED / CLOSED
- Delivery / read / failed lifecycle UI
- Call tracking
- Lead capture and classification
- Beyond Assistant + intent router + ERP tools
- Human handover queue
- WhatsApp-origin quotations that create **existing** `quotations` rows
- Inbound internship submission
- Employee WhatsApp check-in / location
- Document retrieval + OTP
- Tenant / property / maintenance
- Bill-payment requests
- Appointments + Google Calendar
- Automation engine, alerts, management brief, analytics
- Hub Command Center UI and `whatsapp.*` permissions
- Real-time inbox (optional; polling is enough for Phase 1)
- Meaningful automated tests

---

## 8. D. Proposed database changes

**Additive migrations only.** Compare schema again at the start of each phase.

### Phase 1 (foundation)

- `whatsapp_contacts` — `normalized_phone` unique, `display_phone`, `wa_name`, `blocked_at`
- `whatsapp_contact_links` — polymorphic (`linkable_type` / `linkable_id`) so one phone can be user + customer + employee + …
- `whatsapp_conversations` — contact, `mode` (AI/HUMAN/PAUSED/CLOSED), unread, last activity, assigned user
- `whatsapp_messages` — conversation, direction, type, `provider_message_id` unique, body, media refs, status, queued/sent/delivered/read/failed timestamps, error, AI vs human
- `whatsapp_webhook_events` — `provider_event_id` unique, event type, raw payload, verified, processed_at
- `whatsapp_calls` — provider call id, from, video flag, status, linked contact/lead
- `whatsapp_settings` — non-secret Hub settings (assistant enabled, greeting). **Never store API keys here.**

Indexes: `normalized_phone`, `provider_message_id`, `provider_event_id`, conversation_id, contact_id, status, `created_at`.

### Later phases (do not create now)

`leads`, `lead_activities`, `whatsapp_handoffs`, `whatsapp_automations` / `_runs`, `whatsapp_document_requests`, `whatsapp_verifications`, `appointments`, `appointment_reminders`, `alerts`.

### Do not replace

`internship_notification_logs`, `message_delivery_*`, `wa_announcements*`, `quotations`, `bookings`, `attendances`, `be_timesheet_*`, `sales`, `payments`.

---

## 9. E. Proposed backend architecture

```
POST /api/webhooks/wasender
        │
        ▼
Verify X-Webhook-Signature
        │
        ▼
Persist whatsapp_webhook_events (idempotent)
        │
        ▼
HTTP 200
        │
        ▼
Queue / after-response
        │
        ├── WaSenderEventParser
        ├── WhatsAppIdentityService   (WhatsAppPhone + contact_links)
        ├── Conversation / Message / Call writers
        └── (Phase 3+) Beyond Assistant tools → existing ERP services

Outbound (unchanged path):
NotificationRouter → BeyondWasenderService → Wasender
                 └── (later) also write whatsapp_messages
```

Suggested layout, adapted to existing `app/Services` conventions:

```
app/Contracts/WhatsApp/WhatsAppProviderInterface.php
app/Services/WhatsApp/Providers/WaSenderProvider.php   # wraps BeyondWasenderService
app/Services/WhatsApp/WhatsAppIdentityService.php
app/Services/WhatsApp/WaSenderEventParser.php
app/Http/Controllers/WhatsApp/WaSenderWebhookController.php
app/Http/Controllers/WhatsApp/WhatsAppHubController.php
app/Jobs/ProcessWasenderWebhook.php
app/WhatsApp/*   # Eloquent models
```

`WaSenderProvider` must **not** contain rental/finance/internship business rules.

Webhook stays **outside** `auth` and `intern.compliance`. Hub UI stays inside the existing admin middleware group.

---

## 10. F. Proposed frontend architecture

Add **WhatsApp Hub** to `SiteMenu::sideItems()` and a sidebar collapse matching Announcements.

Phase 1 screens (Blade, existing typography/colors):

- Command Center — session status (reuse `whatsapp:status` / Wasender GET), messages today from existing logs + new tables, failed sends. Cards for tenants/appointments/AI **omitted or labelled “not in ERP yet”**.
- Conversations — empty until webhooks fire. Desktop: list | thread | contact panel. Mobile: list → thread → details.
- Message Tracking — table stub wired to `whatsapp_messages` + existing internship/letter logs where useful.
- Settings — connection status, configured number, webhook health. **No secrets.**

No new design system. No React admin. Date filters: Today / Yesterday / 7 / 30 / custom, from real queries.

---

## 11. G. Security considerations

- Verify `X-Webhook-Signature` against `WASENDER_WEBHOOK_SECRET`. Reject unsigned payloads.
- Do not expose API keys, session tokens, or webhook secrets in Hub UI or settings APIs.
- Do not commit `.env`.
- Webhook route is public: rate-limit, persist first, process async.
- Identity is not authorization. Financial / grade / contract documents need Phase 7 OTP (hashed, expiry, rate-limit).
- One phone, many roles: never assume “customer only”.
- Block/mute abusive contacts.
- AI (later) must call constrained ERP tools; never invent prices, stock, balances, grades, or payment success.
- Additive migrations; no destructive table renames.
- Isolated public pages (`/pangwayu/remember`, `/mambole`) stay untouched.

---

## 12. H. Potential conflicts with existing functionality

1. **Third send path** — new code that POSTs Wasender directly would bypass the lock and break auto-grade / internship bursts.
2. **`QUEUE=sync` vs 200-quickly** — must persist the webhook before heavy work, even without Redis.
3. **Dual RBAC** — follow announcements: seed Spatie names and grant roles 1–2; controllers still use `role_id` + `hasPermissionTo`.
4. **Quotation vocabulary** — Hub labels ≠ `quotation_status` integers.
5. **Internship double-send** — Hub must not re-send daily tasks or grade messages that `InternshipProgramService` already sends.
6. **Wasender 5-second protection** — inbound auto-replies (Phase 3+) must use the same lock; bursts still need spacing.
7. **No tenant / appointment / AI data** — Command Center must not invent those stats.
8. **`Leader` vs lead** — do not reuse the About-Us `Leader` model for CRM.
9. **Supervisor “chat”** — `InternSupervisorChat` is web-reply links, not a WhatsApp inbox. Do not confuse the two.
10. **React leftovers** — ignore `BeyondTechWorld/src` Wasender/webhook UI; that is not this ERP.

---

## 13. I. Recommended implementation order

Do **not** start Phase 1 until this audit is approved.

| Phase | Scope |
|-------|--------|
| **0** | This audit (`WHATSAPP_HUB_AUDIT.md`) |
| **1** | Provider wrap, webhook, verify, idempotency, contacts, identity, conversations, messages, delivery/read, calls, session status, permissions, Hub shell |
| **2** | Inbox, unknown-number leads, classification, AI/HUMAN modes, handover, staff replies, context panel, quick actions |
| **3** | Beyond Assistant intent router, ERP tools, clarification, fallback (no arbitrary DB writes) |
| **4** | Rental search / price / availability / quotation via **existing** `quotations` + PDF |
| **5** | Inbound internship submit + media; reuse existing grade/next-task WhatsApp |
| **6** | Check-in / out → existing `Attendance` / timesheets |
| **7** | Document retrieval + OTP |
| **8** | Tenants + bill-pay (**requires property module first**) |
| **9** | Appointments + Google Calendar |
| **10** | Automations, alerts, management brief, analytics |

Each later phase: inspect → document → migrate → backend → frontend → permissions → tests → do not advance on errors → update `WHATSAPP_HUB_IMPLEMENTATION_STATUS.md`.

---

## 14. J. Exact files / modules expected to change in Phase 1

### New

- `laravel-app/app/Contracts/WhatsApp/WhatsAppProviderInterface.php`
- `laravel-app/app/Services/WhatsApp/Providers/WaSenderProvider.php`
- `laravel-app/app/Services/WhatsApp/WhatsAppIdentityService.php`
- `laravel-app/app/Services/WhatsApp/WaSenderEventParser.php`
- `laravel-app/app/Http/Controllers/WhatsApp/WaSenderWebhookController.php`
- `laravel-app/app/Http/Controllers/WhatsApp/WhatsAppHubController.php`
- `laravel-app/app/Jobs/ProcessWasenderWebhook.php`
- Models under `laravel-app/app/WhatsApp/`
- `laravel-app/database/migrations/2026_09_22_*_whatsapp_hub_foundation.php` (tables + Spatie `whatsapp.*` permissions)
- `laravel-app/resources/views/whatsapp_hub/*`
- `laravel-app/tests/Feature/WhatsAppWebhookTest.php`
- `laravel-app/tests/Unit/WhatsAppIdentityTest.php`
- `WHATSAPP_HUB_IMPLEMENTATION_STATUS.md` (after Phase 1, not now)

### Edit

- `laravel-app/app/Support/SiteMenu.php` — `whatsapp` side item
- `laravel-app/resources/views/layout/main.blade.php` — sidebar block (Announcements permission pattern)
- `laravel-app/routes/api.php` — `POST webhooks/wasender`
- `laravel-app/routes/web.php` — Hub routes inside `auth` group
- `laravel-app/config/services.php` + `laravel-app/.env.example` — `WASENDER_WEBHOOK_SECRET`
- `laravel-app/app/Console/Kernel.php` — optional session-health check
- `laravel-app/app/Services/BeyondWasenderService.php` — **only** if needed to emit `msgId` into Hub; keep send behaviour

### Do not touch in Phase 1

Internship auto-grade / `tryReleaseNext` notify order; birthday / memorial pages; `quotations` / `bookings` / `sales` schema; React trees; production `.env`.

### Proposed Phase 1 permissions (seed like announcements)

`whatsapp_module`, `whatsapp.view`, `whatsapp.manage`, `whatsapp.conversations`, `whatsapp.reply`, `whatsapp.takeover`, `whatsapp.leads`, `whatsapp.calls`, `whatsapp.documents`, `whatsapp.attendance`, `whatsapp.appointments`, `whatsapp.automations`, `whatsapp.analytics`, `whatsapp.settings`.

Grant to roles 1 and 2. Extra names can exist before their screens exist.

---

## 15. API / frontend / security summary

**API (Phase 1)**

- Public: `POST /api/webhooks/wasender`
- Admin (auth): Hub index, conversations index/show, message tracking, settings (no secrets)

**Frontend (Phase 1)**

- Sidebar item + four Blade screens listed in §10
- Reuse existing admin chrome

**Security (Phase 1)**

- Signature verification + idempotency + no secrets in UI + webhook outside intern middleware

---

## 16. Success criteria for later work

WhatsApp becomes a secure operational interface into BeyondTechWorld, not only a notification channel — while remaining **one source of truth** for rentals, finance, internship, employees, and (when built) tenants.

At every stage: security, auditability, reuse of existing modules, reliability, maintainability, ease of use.

---

## 17. Phase 0 stop

This file is the Phase 0 deliverable.

**Do not begin Phase 1** (provider wrap, webhook, tables, Hub menu, or tests) until this audit has been reviewed and Phase 1 is explicitly approved.
