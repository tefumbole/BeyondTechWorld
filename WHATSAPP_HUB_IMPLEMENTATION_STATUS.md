# WhatsApp Hub — Implementation Status

PHASE: 6 — Attendance & Field Operations  
STATUS: Live validation is in progress and is not closed. Office check-in, checkout, timesheet, corrections, intern check-in, outside geofence, and handover passed on production. Invalid location, stale location, an identical webhook retry, field checkout, and location-coordinate cleanup were not reconfirmed after a fix (`3c3e68e`) because the server stopped accepting SSH. Do not start Stage 7.

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

**101 tests, 483 assertions — OK** (`./vendor/bin/phpunit --filter WhatsApp`).

That run includes Stage 6 and the Stage 1–5 regression.

### Live validation — 23 September 2026

Production HEAD at the start of the run was `a7985a0`, then `23635bc`, then `3c3e68e`. Migration `2026_09_23_160000_extend_attendance_for_whatsapp` is batch 196. The queue error log was empty and WaSender was connected. Messages to the controlled staff numbers were sent (`SENT`).

Controlled records, by id only: office user 274 / employee 1 / conversation 3; intern user 284 / enrolment 4; field user 278 / employee 3; event `JOB-9001`; outside fixture employee 4 on `JOB-9002`. No existing attendance rows were present before the run (count was 0).

| Test | Result | Evidence |
| --- | --- | --- |
| A Employee check-in | PASS | Attendance 2, source whatsapp, check-in 14:18:38, conversation 3, WhatsApp SENT |
| B Duplicate check-in | PASS | Still one row for that employee today. Reply: already checked in at 14:18 |
| C Status | PASS | Reply used attendance 2: Checked In, started 14:18 |
| D Check-out | PASS | Attendance 2 closed at 14:19:52. Duration 0h 1m |
| E Timesheet | PASS | Timesheet `7787556a-bd3d-47ae-892c-bc3b45b6b58b`, 0.02 hours, status submitted. Payroll stayed 0 |
| Server time | PASS | Text said 8:00 AM. Stored check-in is 14:18:38. A later attempt did not open another row |
| F Field job check-in | PASS | `CHECK IN JOB 9001` asked for a WhatsApp location. No row until a location was accepted |
| G Assignment validation | PASS | `CHECK IN JOB 0001` was refused. No event name was disclosed. No row |
| H WhatsApp location | FAIL | The location pin asked which role instead of finishing check-in. Fix is deployed and was not retested |
| I Geofence inside | PASS | Attendance 6, `LOCATION_VERIFIED`, distance 0 m, radius 150 m. WhatsApp said location verified. Venue coordinates were set on `JOB-9001` only |
| Outside geofence | PASS | Attendance 5, `LOCATION_REVIEW_REQUIRED`, distance 3214 m, radius 150 m. Reply did not say verified |
| J Invalid location | FAIL | Reply asked which role. Nothing was recorded, but the error text was wrong. Fix not retested |
| K Stale location | FAIL | Reply asked which role. Fix not retested |
| L Intern check-in | PASS | Attendance 4, intern user 284, no employee id. Task 3 stayed `available` |
| M Intern schedule | PASS | Wednesday start 08:30. Check-in 14:20 stored status 0 |
| N My hours | PASS | Today and this week came from attendance and the timesheet, including the natural-language question |
| O Missing checkout | PASS | Open row 1 for 2026-09-22 blocked a new check-in. Checkout stayed empty |
| P Correction request | PASS | Correction 1 is `PENDING`. Attendance 1 was not changed |
| Q Supervisor approval | PASS | User 279 was denied. User 1 approved 17:00. Timesheet for that day is 8 hours, status submitted |
| Unauthorized correction | PASS | User 279 could not change the checkout |
| R Unknown number | PASS | Handover reply. No attendance and no new user |
| S Rapid commands | PASS | Four parallel check-ins left one open row. Two overlapping check-ins for employee 2 also left one row |
| T Worker retry | FAIL | The two posts were not byte-for-byte identical, so the duplicate flag was not proven. Not retested |
| U Human handover | PASS | Conversation 3 switched to HUMAN. A following CHECK IN got no assistant reply |
| Natural language | PASS | “I've arrived” mapped to the open session. “I'm leaving” checked out |
| Multi-role | PASS | CHECK IN asked Employee or Internship and wrote nothing until a role was chosen |
| Location privacy | PASS | Attendance page shows the location column only for an authorized role. Raw coordinates were not in the page |
| Location retention | NOT RUN | The cleanup command was not executed. Attendance 6 still has coordinates |
| Field checkout / event safety | FAIL | Event `JOB-9001` stayed `planning`, but the field session (attendance 6) was not checked out. Fix not retested |
| Payroll safety | PASS | Payroll count stayed 0. Timesheet status stayed submitted |
| Stage 5 current task | PASS | Reply named the current enrolment task. Read only |
| Stage 5 submission | NOT RUN | The live task asks for a practical artifact. Nothing was submitted |

Stage 4 live WhatsApp validation was not repeated in this run. It stays not live-tested. Automated tests are not treated as a live pass.

Stage 6 is not marked completed. Stage 7 has not started.

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
