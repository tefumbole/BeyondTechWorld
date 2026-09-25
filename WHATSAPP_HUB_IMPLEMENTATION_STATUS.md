# WhatsApp Hub — Implementation Status

PHASE: 8 — Property, Tenant & Bill Payment Operations  
STATUS: DEPLOYED. Automated tests passed (128 tests, 727 assertions). Live A–W were not run: SSH to the server timed out after the deploy. The site webhook still returned 200. Stage 9 has not started.

Date: 23 September 2026

---

## Conversational assistant upgrade

Date: 25 September 2026. Report: `WHATSAPP_ASSISTANT_CONVERSATIONAL_UPGRADE.md`.

Ordinary chat now uses conversation history and the existing ERP tools. Checkout, OTP, documents, property, bills, and human handover still win over chat. AI-first applies to new conversations only and stays off until an admin enables it. Automated tests: 140 tests, 782 assertions, OK. Stage 9 has not started.

---

## Owner control and group intelligence

Date: 25 September 2026. Report: `WHATSAPP_GROUP_INTELLIGENCE_IMPLEMENTATION.md`.

A mapped ERP owner can control AI from a private WhatsApp chat, including a confirmed bulk switch. Group webhooks are stored only after a group is explicitly enabled, and the default enabled mode is Monitor. Discovered groups stay off. Automated tests: 153 tests, 852 assertions, OK. This was not deployed. Stage 9 has not started.

---

## Stage 9 — Appointments and Google Calendar

Date: 25 September 2026. Report: `WHATSAPP_APPOINTMENTS_IMPLEMENTATION.md`.

An appointment is an ERP row. WhatsApp offers only configured windows and creates the row after the customer chooses a time. Google Calendar is updated only when credentials are set; otherwise the booking stays in the ERP and the reply says the calendar was not copied. Reminders stay off until `APPOINTMENT_REMINDERS` is enabled. Automated tests: 160 tests, 898 assertions, OK. This was not deployed. Stage 10 has not started.

---

## STAGE 8 — Property, Tenant & Bill Payment Operations

Audit: `WHATSAPP_HUB_STAGE8_AUDIT.md`.

A property/tenant module did not exist. Equipment rental (Product → Booking, including `pdf/rent_pdf.blade.php`) stays separate. Stage 8 added the ERP foundation instead of a `whatsapp_tenants` table.

Path: WhatsApp → Stage 1 identity (customer, and tenant when an active tenancy exists) → Beyond Assistant tool → property service → ERP row → `property_activities` → WhatsApp reply.

### ERP foundation

| Record | Table |
| --- | --- |
| Property | `properties` |
| Unit | `property_units` (`AVAILABLE`, `OCCUPIED`, `MAINTENANCE`, `INACTIVE`) |
| Tenancy | `tenancies`, linked to `customers`. One `ACTIVE` row per unit (`active_unit_lock`). |
| Rent obligation | `rent_obligations`. `property:generate-rent` is idempotent per tenancy and period. |
| Rent payment | `property_rent_payments`. Staff/ERP only. A WhatsApp claim does not insert one. |
| Maintenance | `property_maintenance_requests` plus validated attachments. |
| Utility account | `property_utility_accounts`, readable only for that unit. |
| Bill request | `bill_payment_requests`. A request is not a payment. |

Screens: `/admin/properties`, maintenance, and bills. WhatsApp Hub → Tenant Operations and Bill Payments. Command Center and Diagnostics read these tables. Permissions are limited to roles 1–2, not every Hub user.

### Identity and routing

`WhatsAppIdentityService` adds a `tenant` link for each active tenancy. A person can still be customer, employee, intern, and tenant. “What do I owe?” asks whether they mean rent or another account. `CHECK OUT` and the other Stage 6 commands stay ahead of a pending bill, maintenance photo, or tenant clarification.

### Documents

Stage 7 OTP, ownership, and path checks are reused. New registry keys: `RENT_RECEIPT`, `RENT_STATEMENT`, `TENANCY_AGREEMENT` (file must already exist), `BILL_PAYMENT_RECEIPT` (only after `PAID`). `CUSTOMER_RECEIPT` is still unavailable.

### Maintenance and Stage 6

Categories come from a fixed list. Priority stays `NORMAL` unless the text matches a configured danger phrase, which sets `URGENT` and returns the configured emergency instruction. No telephone number is invented. Assigning staff uses `employees`. A field check-in can store `attendance_id`. Checking out does not resolve the request.

### Bill payments

Confirmation moves a complete request to `UNDER_REVIEW`. It does not debit a wallet, MoMo number, or card. PawaPay, Campay, and Stripe are not called. `PAID` is set only by `BillPaymentService::applyProviderResult`, which the signed webhook uses. The same provider event id cannot be applied twice. A timeout or unknown result becomes `PENDING_CONFIRMATION`. Reconciliation reads stored events and does not start a second charge. Service fee is the configured amount, default 0.

Payment provider calls against real money were not made.

`property:rent-reminders` records one row per obligation and reminder type. A second run does not send again. After the obligation is paid, a later run does not send an overdue notice. The scheduler for reminders stays off until `PROPERTY_RENT_REMINDERS` is enabled. Rent generation is scheduled daily and is idempotent.

### Automated tests

`./vendor/bin/phpunit --filter WhatsApp` — **128 tests, 727 assertions, OK.** That includes Stage 8 and the Stage 1–7 regression.

### Live validation

Deploy of `212ef48` completed. Migration `2026_09_23_200000_create_property_tenant_foundation` ran. Only `beyondtechworld-whatsapp-queue` was restarted. `GET /api/webhooks/wasender` returned 200 after SSH dropped.

The controlled live script did not run. SSH to the server timed out, so A–W are **NOT RUN**. They were not marked PASS. No production payment was taken. Sandbox provider success was not executed on the server.

| Check | Result |
| --- | --- |
| A Tenant balance | NOT RUN |
| B Wrong tenant | NOT RUN |
| C Rent due | NOT RUN |
| D Payment history | NOT RUN |
| E False payment claim | NOT RUN |
| F Rent receipt | NOT RUN |
| G Tenancy agreement | NOT RUN |
| H Maintenance request | NOT RUN |
| I Maintenance photo | NOT RUN |
| J Maintenance status | NOT RUN |
| K Wrong maintenance id | NOT RUN |
| L Field attendance does not close maintenance | NOT RUN |
| M Bill request is not paid | NOT RUN |
| N Bill image is provisional | NOT RUN |
| O Confirmation does not debit | NOT RUN |
| P Provider success | NOT RUN (sandbox was not executed) |
| Q Provider failure | NOT RUN |
| R Timeout / no second debit | NOT RUN |
| S Duplicate webhook | NOT RUN |
| T Duplicate WhatsApp bill request | NOT RUN |
| U Employee + tenant then CHECK OUT | NOT RUN |
| V Unknown number | NOT RUN |
| W Reminder idempotency | NOT RUN |
| Production payment | NOT RUN |

### Known limitations

- No BeyondTechWorld bill-pay client is connected. Requests stop at staff review until a signed provider result arrives.
- Monthly and yearly rent generation are supported. Other frequencies are stored and skipped by the generator.
- Rent reminders do not send until the reminder flag is on, except a direct command run.
- A tenancy agreement is sent only when staff have stored the file.
- Stage 6 attendance is linked by id. It does not create a calendar event.

### Stage 9 readiness

Stage 9 (appointments and Google Calendar) was not started. It can reuse identity, the assistant registry, idempotency, audit, and Stage 7 verification. Do not start it without approval.

---

## STAGE 7 — Secure Document Retrieval & OTP

Audit: `WHATSAPP_HUB_STAGE7_AUDIT.md`.

The assistant only names the document. `WhatsAppDocumentRegistry` lists what exists. `DocumentAuthorizationService` decides ownership. `WhatsAppVerificationService` issues and checks the code. `QuotationController::buildQuotationPdf` and `SaleController::buildSaleInvoicePdfBinary` produce the file. `WhatsAppProviderInterface` sends it.

### Supported documents

| Key | Sensitivity | WhatsApp |
| --- | --- | --- |
| CUSTOMER_QUOTATION | VERIFIED | Yes, existing quotation PDF |
| CUSTOMER_INVOICE | VERIFIED | Yes, existing sales-invoice PDF |

Not registered for sending, because the ERP has no safe file to reuse: customer receipt, customer contract, payslip, employment contract, timesheet PDF, mission order, internship letter, internship assessment, internship certificate. A payment claim does not create a payment or a receipt. A certificate request does not create a certificate. Payslip verification on the public site is unchanged and is not a WhatsApp sender.

### OTP

`whatsapp_verification_challenges` stores HMAC-SHA256 of a 6-digit `random_int` code. The plaintext is sent through the existing provider and is not stored on the challenge, in assistant activity, or in conversation memory. The stored WhatsApp message says only that a code was sent.

Defaults, all from config: TTL `WHATSAPP_OTP_TTL_MINUTES` 5, attempts `WHATSAPP_OTP_MAX_ATTEMPTS` 5, resend cooldown `WHATSAPP_OTP_RESEND_COOLDOWN_SECONDS` 60, hourly `WHATSAPP_OTP_HOURLY_LIMIT` 5, daily `WHATSAPP_OTP_DAILY_LIMIT` 10, session `WHATSAPP_VERIFICATION_SESSION_MINUTES` 20. A new code invalidates the previous active code for that contact. During the cooldown the active code is kept and no second message is generated. A used code cannot open another session. The session scope is `CUSTOMER_DOCUMENTS`, `EMPLOYEE_DOCUMENTS`, or `INTERN_DOCUMENTS` for that identity only.

### Ownership and requests

`whatsapp_document_requests` records the contact, identity, document type, resolved id, sensitivity, status, and failure code. It does not store the PDF contents. A supplied id is loaded only from rows owned by the resolved identity. The public refusal is “I couldn't provide that document for this account.” Paths containing `..`, a URL, or a file outside `storage/app` and `public/quotation` are rejected. User text is never used as a filesystem path.

Inbound order: webhook idempotency, human handover, attendance commands such as CHECK OUT, a 6-digit code only while a challenge is pending, then the document request, then other assistant intents.

### Hub

WhatsApp Hub → Documents shows request counts and rows without file contents or codes. Command Center and Diagnostics use the same tables. Diagnostics never shows the code, the hash, the AI key, or the WaSender secret.

Permissions added: `whatsapp.documents.view`, `whatsapp.documents.manage`, `whatsapp.documents.retry`, `whatsapp.verification`, `whatsapp.verification.invalidate`. They do not bypass ownership. Staff can retry a failed send or invalidate a verification.

### Tests

`./vendor/bin/phpunit --filter WhatsApp` — 118 tests, 632 assertions, OK. That includes Stage 7 and Stages 1–6. Stage 4 “send me a quotation” / “send the quotation” still creates a rental draft. “Send my quotation” is the retrieval path.

### Deploy

Commit `8a4dd3c` is on the server. Migration `2026_09_23_180000_create_whatsapp_document_verification` ran. `beyondtechworld-whatsapp-queue` was restarted. HTTPS `GET /api/webhooks/wasender` returned 200 after SSH became unreachable. The letterhead warning is the existing branding notice.

### Live validation

Run on the server against customer 124, the same phone as the Stage 6 fixture employee. Quotation `S7-CONTROLLED` (id 26) was created for that account. One verification code and two quotation PDFs were sent to that number. The code was not stored in plaintext. The conversation was returned to HUMAN.

| Check | Result |
| --- | --- |
| A Customer quotation | PASS — code required, hash stored, existing PDF sent |
| B Wrong customer quotation | PASS — denied, the other id was not echoed |
| C Employee payslip | NOT RUN — no payslip PDF; nothing was sent |
| D Wrong OTP | PASS — attempt recorded, no document |
| E Expired OTP | PASS |
| F Max attempts | PASS — challenge invalidated |
| G Resend cooldown | PASS |
| H OTP replay | PASS — one session |
| I Another person's document after a valid session | PASS |
| J Receipt | PASS — the fixture has no payment, so no receipt was created |
| K False payment claim | PASS — payment rows unchanged |
| L Employment contract | NOT RUN — no employee contract file |
| M Internship document | NOT RUN — no issued internship file |
| N Certificate | PASS — reported not available; none created |
| O Unknown number | PASS — no code, payslip not sent |
| P Arbitrary path | PASS |
| Q Duplicate webhook | PASS — identical payload returned duplicate; the same provider message did not open a second request |
| R Induced send failure | NOT RUN — the live provider was not forced to fail |
| S Verified session, then expiry | PASS — a second quotation was sent inside the session; after expiry a new code was required |
| T Employee and intern | PASS — asked which document, then kept the employee context |
| CHECK OUT while a code was pending | PASS — checkout ran; the reply said today's attendance is already closed; no new attendance row |

### Stage 8

Not started. Stage 8 is property and tenant operations. This stage did not audit a property or tenant module and did not add tenant WhatsApp behaviour. Stage 8 has to begin with that audit.

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

Appointments, Google Calendar, automations, management brief, and advanced analytics remain deferred. Property, tenant, and bill-payment requests are Stage 8.

---

## PHASE 1 FOUNDATION (shipped)

Provider abstraction, webhook, identity, conversations, tracking, calls, diagnostics, settings, queue worker. See git history around `08b1a1c` / `fb0e9be` / `f352432` / `ce2d82e`.
