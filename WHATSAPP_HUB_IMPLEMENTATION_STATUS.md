# WhatsApp Hub — Implementation Status

PHASE: 6 — Attendance & Field Operations  
STATUS: COMPLETED AND LIVE VALIDATED. Stage 7 has not started.

Date: 23 September 2026

---

## STAGE 6 — Attendance & Field Operations

Audit: `WHATSAPP_HUB_STAGE6_AUDIT.md`.

WhatsApp is another way to open and close the attendance the ERP already stores. `CHECK IN`, `CHECK OUT`, `STATUS`, `MY HOURS`, and `MY ASSIGNMENT` are recognized before any AI call. Natural wording such as “I've arrived” or “I'm leaving” uses the same tools. A time typed in the message is ignored. The server clock is the check-in and check-out time.

### Existing architecture reused

- `attendances` — one row per person per day. Empty checkout is the open session. A second check-in the same day does not open another row. After checkout, that day stays closed.
- `hrm_settings` — expected office times. Late is recorded as the existing status flag. No penalty.
- `be_working_week` — intern schedule for that user. Not a global Monday–Friday.
- `be_timesheet_entries` through `TimesheetService::refreshDayBalance` — checkout writes hours. Overtime stays pending for a supervisor. Payroll is not written.
- `event_assignments`, `event_worker_profiles`, `btw_events`, and `EventTimesheetService` — field check-in only when that person is assigned. Check-out does not complete the event or the rental.
- Internship task status is not changed by check-in.

### Migration

`2026_09_23_160000_extend_attendance_for_whatsapp.php`

Adds WhatsApp source, message id, conversation, location, distance, and assignment columns on `attendances`. `employee_id` and `checkout` may be null so an intern without an employee row, and an open session, can be stored. Adds venue coordinates on `btw_events` only as empty columns. Creates `attendance_correction_requests` and `whatsapp_attendance_activities`.

### Services

- `AttendancePolicyService` — who may check in, whether location or a job is required, expected times, duplicate and closed-day state.
- `AttendanceLocationService` — Haversine distance. Results are `LOCATION_VERIFIED`, `LOCATION_REVIEW_REQUIRED`, or `UNVERIFIED`. No coordinates are invented. A location older than `WHATSAPP_ATTENDANCE_LOCATION_MAX_AGE_MINUTES` (default 15) does not count. Coordinates older than `WHATSAPP_ATTENDANCE_LOCATION_RETENTION_DAYS` (default 90) are cleared by `whatsapp:prune-webhooks`. The attendance row stays.
- `AttendanceWhatsAppService` — identity, policy, the existing attendance row, timesheet, event timesheet, audit, and confirmation. Success is returned only after the database write.

Office `CHECK IN` does not ask for a location. `CHECK IN JOB …` does, unless field location is turned off. Outside the radius the attendance is saved for supervisor review. It is not called verified. An unknown number creates no attendance. Employee and intern together are asked which context to use. A previous day's open session blocks a new check-in. The missing checkout time is not invented.

### Commands and tools

Deterministic: `ATTENDANCE_IN`, `ATTENDANCE_OUT`, `ATTENDANCE_STATUS`, `ATTENDANCE_HOURS`, `ATTENDANCE_ASSIGNMENT`, `ATTENDANCE_CORRECTION`.

Tools: `get_attendance_status`, `check_in`, `check_out`, `get_work_hours`, `get_current_assignment`, `check_in_assignment`, `check_out_assignment`, `validate_assignment_location`, `request_attendance_correction`, `get_attendance_correction_status`.

Approve, override location, and change historical attendance are privileged. The assistant can only file a `PENDING` correction. A staff member with `whatsapp.attendance.corrections` supplies the corrected time.

### Hub

WhatsApp Hub → Attendance lists who is checked in, field staff on site, missing checkouts, location review, pending corrections, and recent actions. Command Center and Diagnostics use the same counts. Location is shown only with `whatsapp.attendance.location`. One employee cannot read another person's hours by sending their id.

Permissions: `whatsapp.attendance`, `.view`, `.manage`, `.location`, `.corrections` for roles 1–2. They do not replace payroll permissions.

### Tests

**103 tests, 511 assertions — OK** (`./vendor/bin/phpunit --filter WhatsApp`).

That run includes Stage 6, the location-pin and checkout regressions, identical webhook replay, and the Stage 1–5 regression.

### Live validation — 23 September 2026

Production functional code for the retest was `3c3e68e`. Migration `2026_09_23_160000_extend_attendance_for_whatsapp` is batch 196. The queue had no failed jobs. WaSender was connected.

| Test | Final Result | Evidence |
| --- | --- | --- |
| Office check-in | PASS | Attendance 2, 14:18:38, WhatsApp SENT |
| Duplicate check-in | PASS | One row. Already checked in at 14:18 |
| Status | PASS | Checked In, started 14:18 |
| Checkout | PASS | Attendance 2 closed at 14:19:52 |
| Timesheet | PASS | 0.02 hours, status submitted. Payroll stayed 0 |
| Field job | PASS | Job 9001 asked for a location before any row |
| Assignment validation | PASS | Job 0001 refused. No event details disclosed |
| Location pin | PASS | After the role fix, the pin kept Employee. Attendance 7, no second role question |
| Inside geofence | PASS | Attendance 6, `LOCATION_VERIFIED`, 0 m, radius 150 m. Repeated on attendance 7 |
| Outside geofence | PASS | Attendance 5, `LOCATION_REVIEW_REQUIRED`, 3214 m. Not called verified |
| Invalid location | PASS | Invalid pin was refused. No coordinates stored. Audit stayed clean |
| Stale location | PASS | A 3-hour-old pin was refused and a fresh location was requested |
| Intern check-in | PASS | Attendance 4, intern 284. Task 3 stayed available |
| Intern schedule | PASS | Wednesday 08:30. Check-in 14:20 stored status 0 |
| My hours | PASS | Today and this week from the ERP, including natural language |
| Missing checkout | PASS | Open 22 September row blocked a new check-in. No invented time |
| Correction | PASS | Correction 1 stayed PENDING until a supervisor approved it |
| Supervisor approval | PASS | User 279 denied. User 1 set 17:00. Timesheet 8 hours, submitted |
| Unknown number | PASS | Handover. No attendance and no new user |
| Rapid duplicate | PASS | Parallel check-ins left one open row |
| Identical webhook retry | PASS | The same location payload posted twice. Duplicate flag set. One message, one attendance |
| Human handover | PASS | Conversation 3 went HUMAN. The next CHECK IN got no assistant reply |
| Field checkout | PASS | Attendance 6 closed at 14:53:12. Duration 0h 31m. WhatsApp SENT. Event `JOB-9001` stayed `planning`. Assignment `checked_out` |
| Location cleanup | PASS | Disposable attendance 8 lost coordinates only. Check-in 08:00 and checkout 09:00 remained. Attendance 6 coordinates were kept |

Stage 5 current task was read from the live enrolment and passed. Stage 5 submission stays **NOT RUN**. The live task needs a real artifact, and none was sent.

Stage 4 live WhatsApp validation was not repeated. It stays not live-tested.

### Infrastructure incident during live validation

SSH from the validation client to port 22 timed out for about 25 minutes, from roughly 13:25 to 13:50 UTC. The server had been up 106 days and was not rebooted. Load was 0.14, disk was 13% used, and about 6.5 GB of memory was free. `sshd`, nginx, and PHP-FPM stayed active. Fail2ban was not running. The public site and the WhatsApp webhook answered HTTP 200. The queue worker stayed online with no failed jobs. ICMP is blocked on this host and was not the outage. The next SSH attempt was accepted with the existing key. This was a transient network path to SSH, not a Stage 6 defect. No rollback was done.

The first pass failed the location pin, invalid location, stale location, identical replay, and field checkout because a chosen role was dropped and `CHECK OUT` was treated as the pending check-in. Those were repeated after `3c3e68e` and passed. Earlier passes were kept.

Stage 7 has not started.

### Known limitations

- One attendance row per person per day. After checkout, that day is closed until a staff correction.
- Breaks are not implemented. The ERP has no break records.
- Payroll, payable overtime, and discipline are not decided from WhatsApp.
- Venue coordinates stay empty until staff set them. Until then a field location is `UNVERIFIED`.
- Guest invitation check-in is unchanged.
- An employee who is also an intern must say which role they are checking in as.

### Deployment

Migrate `database/migrations/2026_09_23_160000_extend_attendance_for_whatsapp.php`, then restart only `beyondtechworld-whatsapp-queue`.

### Stage 7

Not started. Stage 7 is secure document retrieval and OTP (invoice, payslip, internship letter, rent receipt). It must use existing ERP documents.

---

## STAGE 5 — Internship WhatsApp Operations

Audit: `WHATSAPP_HUB_STAGE5_AUDIT.md`.

WhatsApp collects work for an existing active enrolment and submits it through `InternshipProgramService::submitAssignment`. Grading, the next task, and supervisor review stay in the internship module. The assistant cannot grade. A locked or unreleased task is not described. “Done” without the required file is not an official submission.

Hub → Internship shows intake counts and links back to the existing enrolment, task, and submission screens. Permissions `whatsapp.internship`, `.view`, `.submit`, `.manage`, and `.media` are for roles 1–2.

### Tests

**87 tests, 388 assertions — OK** (`./vendor/bin/phpunit --filter WhatsApp`).

### Live validation

NOT RUN. A–W stay failed / not passed until someone tests a real intern number on live WhatsApp against a real enrolment.

### Out of scope

Stage 6 (attendance, check-in, location, timesheets) was not started.

---

## PHASE 4 — Rental Intelligence & Quotations

Audit: `WHATSAPP_HUB_PHASE4_AUDIT.md`.

A rental chat collects a `whatsapp_rental_requests` row, checks real stock, prices from `rent_price_per_day`, and creates a normal Draft quotation. Staff approve before any PDF goes out. A WhatsApp “I accept” does not create a booking.

### Availability

On-hand `products.qty`, plus gear on completed bookings (status 1) that is back before the requested start, minus overlapping **pending** bookings (status 2). Draft bookings do not hold stock. Return (status 3) is already back in qty.

“This weekend” and “next month” are treated as ambiguous and the assistant asks for a calendar date. “Next Saturday”, “24 October”, and `24/10` resolve to a real date.

### Quotation

Lines are `product_quotation` on a normal `Quotation` (status 1 Draft). Status names were not changed. `QuotationQuote` stays the client counter-offer table.

Default is **AI prepares → staff Approve & Send**. The assistant does not send the PDF itself. Approve & Send rechecks availability, then uses the existing PDF generator, WaSender document send, and the existing approval link. If the PDF or send fails, the quotation stays Draft.

“I accept the quotation” only returns that secure link after staff have sent it. It does not reserve equipment.

A change such as “8 speakers instead of 2” creates a new quotation and leaves the original total untouched.

Customer-stated prices and discount requests are refused and handed to staff.

Recommendations come from `config/rental_recommendations.php` and are included only when the ERP product exists.

### Hub

WhatsApp Hub → Rentals lists requests, links to the existing quotation editor, and has Approve & Send / Reject. Approve is permission `whatsapp.quotation.approve` (role 1 only). Prepare/view permissions go to roles 1–2. Command Center and Diagnostics show the rental counts. The conversation panel shows event, date, location, guests, status, and total.

### Tests

**63 tests, 238 assertions — OK** (Phase 1 + 2 + 3 + 4).

### Live validation

Not run on live WhatsApp yet. Code is deployed. Do not mark A–P passed until a real test against live products.

### Out of scope

Phase 5 internship submission, document OTP, and calendar booking. Booking reminders remain `bookings:send-reminders`. Overdue notices remain `RentalReturnReminderCron`.

---

## PHASE 3 — Beyond Assistant

Controlled operations assistant. **AI interprets. ERP validates and executes.** The model never receives DB credentials, SQL, or arbitrary Eloquent access.

### Architecture

WhatsApp → WaSender webhook (Phase 1) → conversation/lead (Phase 2) → queued `ProcessAssistantTurn` → `BeyondAssistantService` → intent → policy → registered tool → existing ERP model → composer → `WhatsAppProviderInterface` → WhatsApp.

### Provider

`AiProviderInterface` → `OpenAiProvider` (when `AI_API_KEY` is set) or `NullAiProvider` (tests / missing key). Deterministic intent still handles greeting, services, rental, booking, internship, finance refuse, and human request without a vendor.

### Config (env only — never in Hub UI)

`WHATSAPP_ASSISTANT_ENABLED`, `AI_PROVIDER`, `AI_API_KEY`, `AI_MODEL`, `AI_TIMEOUT`, `AI_MAX_OUTPUT_TOKENS`, `AI_TEMPERATURE`, `AI_MAX_TOOL_ITERATIONS`, confidence thresholds. Hub **Disable Beyond Assistant** is `whatsapp_settings.assistant_enabled`. Both must be on.

### Modes

AI / HUMAN / PAUSED / CLOSED are operational. Human takeover always silences AI. New conversations use AI only when the global switch is on and default mode is AI.

### Tools (read + handover)

Contact, company, services, rental catalogue search (no date availability claim), quotations, bookings, internship summary/task/progress, current lead, list PDFs, `request_human_handover`. Payment summary exists but is policy-blocked (VERIFIED).

### Policy

PUBLIC / RECOGNIZED / VERIFIED / PRIVILEGED. Phase 3 does not auto-disclose balances, receipts, contracts, or employee records. “I paid 500,000” is not ERP confirmation.

### Memory

`assistant_memories` — short-term slots (event type/date/guests), TTL hours, clarification count.

### Screens

WhatsApp Hub → **AI Assistant** (status, knowledge, intents, tools, activity, failures). Conversation: Enable AI, Take Over, Pause, Close, Suggest Reply (draft only). Diagnostics show assistant health without keys.

### Permissions

`whatsapp.ai`, `.ai.manage`, `.ai.knowledge`, `.ai.tools`, `.ai.activity`, `.ai.suggest` — roles 1–2.

### Migration

`2026_09_22_201000_create_whatsapp_hub_phase3.php` — `assistant_knowledge`, `assistant_memories`, `assistant_activities`.

### Tests

**54 tests, 188 assertions — OK** (Phase 1 + 2 + 3).

Covered: disabled/HUMAN/PAUSED silence, greeting, services, rental multi-turn memory, product search disclaimer, booking status, ambiguous bookings, internship task, balance refuse, payment-claim refuse, human handover, low confidence, unauthorized tool, unknown tool, provider failure keeps inbound, duplicate inbound, AI key not leaked, suggested reply does not send.

### Known limitations

- Phase 4 rental availability, draft quotation, and staff-review booking are implemented separately.
- No Phase 5 internship WhatsApp submission or grading.
- No Phase 7 document OTP; VERIFIED finance stays refused.
- No Phase 9 calendar appointments.
- Without `AI_API_KEY`, replies use deterministic intents + approved knowledge (still safe).

### Phase 4 readiness

Rental memory slots (event, date, guests, product) and catalogue search are in place. Do not start availability, pricing engine, auto-quotation, or PDF send until Phase 4 is approved.

### Live validation

**PASSED** — 23 September 2026, on `+250794006160` (Alpha Bridge), with Enable AI on that thread.

Greeting, services, wedding speakers follow-up, balance refused, “speak with someone” handover, and Take Over silence were confirmed by the user. Default conversation mode stays HUMAN.

---

## PHASE 2 COMPLETED (shipped)

Conversations, leads, handover. See git `c7d1971` / `ff6b354`.

---

## PHASE 2 COMPLETED

- Professional inbox filters: unread, awaiting response, mine, unassigned, customers, leads, employees, interns, closed, date, assigned staff, search
- Known-identity context panel (customer / intern / employee) with existing ERP links
- Deterministic lead classifier (keywords only — no AI). Greetings and noise are ignored
- Auto-capture meaningful unknown enquiries as CRM `leads` (not About-Us Leaders)
- Lead classify / assign / status / follow-up / convert-to-customer (no duplicate customers)
- Human handover: take, assign, release, pause, close, reopen
- Internal notes never sent to WhatsApp
- Send existing ERP PDFs via current WaSender provider (path jailed to the app root)
- Unanswered + SLA settings (30 / 60 / 240 minutes by default)
- Command Center Phase 2 cards: new/unassigned leads, follow-ups, awaiting staff, needs attention, workload
- Missed-call follow-up + optional lead
- Lightweight polling on inbox and thread
- Open existing quotation create URL (no second quote system)
- Permissions for roles 1–2 only: `whatsapp.leads`, `whatsapp.leads.manage`, `whatsapp.assign`, `whatsapp.takeover`, `whatsapp.notes`, `whatsapp.documents`
- Additive migration only. Phase 1 send stack not rewritten

Phase 3 / Beyond Assistant / LLM is **not** started.

---

## PHASE 2 MIGRATION

`laravel-app/database/migrations/2026_09_22_191000_create_whatsapp_hub_phase2.php`

Tables: `leads`, `lead_activities`, `whatsapp_conversation_events`, `whatsapp_notes`  
Optional column: `whatsapp_calls.lead_id`  
Settings: `sla_normal_minutes`, `sla_warning_minutes`, `sla_critical_minutes`

---

## PHASE 2 ROUTES

- `GET /admin/whatsapp/leads`
- `GET /admin/whatsapp/leads/{id}`
- `POST /admin/whatsapp/leads/{id}/assign|status|follow-up|note|convert`
- `POST /admin/whatsapp/conversations/{id}/assign|takeover|release|pause|close|reopen|note|document|lead`
- `POST /admin/whatsapp/calls/{id}/follow-up`

---

## TESTS

`tests/Feature/WhatsAppWebhookTest.php`  
`tests/Feature/WhatsAppPhase2Test.php`  
`tests/Unit/WhatsAppIdentityTest.php`  
`tests/Unit/WhatsAppLeadClassifierTest.php`

**33 tests, 129 assertions — OK** (Phase 1 regression included)

Covered in Phase 2: greeting ≠ lead, wedding/sound/LED enquiry → lead, known customer skips auto-lead, enquiry reuse, takeover/release/pause/close/reopen, internal note not sent, reply → WAITING_CUSTOMER, convert existing / new customer, document path jail, leads permission denial, classifier noise vs request.

Existing `tests/Feature/ExampleTest.php` still 500 on `GET /` in the sqlite test env. Not a Hub regression.

---

## LIVE VALIDATION (Phase 1, unchanged)

Do not mark B–K PASSED unless re-tested.

### A. Send ordinary WhatsApp message to BeyondTechWorld

**PASSED** — 22 September 2026. Live inbound from `+250794006160` (Alpha Bridge). User later confirmed staff replies + ticks + calls + outbound workflows also work; Phase 1 CLOSED.

### B–K

**NOT RE-TESTED** in Phase 2 (user previously asserted C/D/H/I/J work).

---

## PHASE 2 LIVE TEST PATH (after deploy)

1. Unknown number texts: `I need sound and LED screens for a wedding`
2. Confirm a lead is created (Audio / LED / Event) and conversation is Awaiting Response
3. Take conversation, add an internal note (must not appear on the phone)
4. Reply from Hub — phone receives it; conversation becomes waiting on customer
5. Convert lead → customer (no duplicate if the number already exists)
6. Create Quotation uses the existing quotation screen
7. Optional: missed call → Follow up + create lead

STOP after that. Do not start Phase 3.

---

## PRODUCTION DEPLOY CHECKLIST

1. Deploy code (`git pull` on `/var/www/beyondtechworld`)
2. `php artisan migrate --force`  
   (runs `2026_09_22_191000_create_whatsapp_hub_phase2`)
3. `php artisan config:clear && php artisan cache:clear && php artisan view:clear`
4. Reload PHP-FPM if the existing deploy script does so
5. Confirm `beyondtechworld-whatsapp-queue` is still running
6. Roles 1–2 already receive the new permissions from the migration

No new `.env` keys. No WaSender webhook change.

Rollback: `php artisan migrate:rollback --step=1` then revert the commit. Phase 1 Hub remains.

---

## DEFERRED (Phase 3+)

Beyond Assistant / LLM, rental/quotation assistant, internship WhatsApp submission, employee check-in, document retrieval/OTP, tenants, bill pay, appointments, Google Calendar, automations, management brief, advanced analytics.

---

## PHASE 1 FOUNDATION (shipped)

Provider abstraction, webhook, identity, conversations, tracking, calls, diagnostics, settings, queue worker. See git history around `08b1a1c` / `fb0e9be` / `f352432` / `ce2d82e`.
