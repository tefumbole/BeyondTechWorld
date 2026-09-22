# WhatsApp Hub — Implementation Status

PHASE: 1 — Foundation  
STATUS: Deployed to production for live validation (do not start Phase 2)

Date: 22 September 2026

---

## COMPLETED

- Provider abstraction (`WhatsAppProviderInterface` → `WaSenderProvider`) delegates to existing `BeyondWasenderService` (send lock and retry unchanged)
- `POST /api/webhooks/wasender` with `X-Webhook-Signature` verification
- Webhook persist + fingerprint idempotency + `ProcessWasenderWebhook` job (`whatsapp` queue)
- Contacts, polymorphic identity links, conversations, messages, calls, webhook events, non-secret settings
- Identity resolver (user, employee, customer, supplier, applicant, intern)
- Incoming message / status / receipt / call handling
- Unknown numbers become WhatsApp contacts only (no CRM leads)
- WhatsApp Hub sidebar + screens: Command Center, Conversations, Message Tracking, Calls, Diagnostics, Settings
- Staff text replies through the provider
- Delivery/read/failed/played tracking
- Session status service (cached; reused by `whatsapp:status`)
- Webhook payload prune command
- Phase 1 Spatie permissions
- Automated tests (16 passing)

Existing outbound notifications (internship, OTP, quotations, rentals, announcements, letters, documents) were not rewritten.

---

## MODIFIED FILES

- `laravel-app/app/Support/SiteMenu.php`
- `laravel-app/resources/views/layout/main.blade.php`
- `laravel-app/routes/api.php`
- `laravel-app/routes/web.php`
- `laravel-app/config/services.php`
- `laravel-app/.env.example`
- `laravel-app/app/Providers/AppServiceProvider.php`
- `laravel-app/app/Console/Kernel.php`
- `laravel-app/app/Console/Commands/WhatsAppStatus.php`
- `laravel-app/phpunit.xml` (test `APP_KEY` only)

---

## DATABASE MIGRATIONS

`laravel-app/database/migrations/2026_09_22_180000_create_whatsapp_hub_foundation.php`

Tables: `whatsapp_contacts`, `whatsapp_contact_links`, `whatsapp_conversations`, `whatsapp_messages`, `whatsapp_webhook_events`, `whatsapp_calls`, `whatsapp_settings`

---

## NEW ROUTES

Public:

- `POST /api/webhooks/wasender` (`whatsapp.webhook`)

Admin (auth + active + intern.compliance):

- `GET /admin/whatsapp` — Command Center
- `GET /admin/whatsapp/conversations`
- `GET /admin/whatsapp/conversations/{id}`
- `POST /admin/whatsapp/conversations/{id}/reply`
- `GET /admin/whatsapp/tracking`
- `GET /admin/whatsapp/calls`
- `POST /admin/whatsapp/calls/{id}`
- `GET /admin/whatsapp/diagnostics`
- `GET /admin/whatsapp/settings`
- `POST /admin/whatsapp/settings`

---

## NEW PERMISSIONS

`whatsapp_module`, `whatsapp.view`, `whatsapp.manage`, `whatsapp.conversations`, `whatsapp.reply`, `whatsapp.calls`, `whatsapp.settings`

Auto-granted to Spatie roles id 1 and 2.

---

## SCREENS

Command Center, Conversations (list + thread + identity panel), Message Tracking, Calls, Diagnostics, Settings.

Default conversation mode: HUMAN. AI mode exists as a stored value only.

---

## SUPPORTED WASENDER EVENTS

- `messages.received` / `message.received`
- `messages.update` / `message.update`
- `message-receipt.update`
- `call`

Unknown events are stored and marked `IGNORED`.

Signature accepted as: exact secret, HMAC-SHA256 of raw body, or `sha256=` + HMAC.

---

## TESTS

`tests/Feature/WhatsAppWebhookTest.php`  
`tests/Unit/WhatsAppIdentityTest.php`

16 tests, 67 assertions — **OK**

Covered: valid/invalid/missing signature, malformed payload, duplicate webhook, unsupported event, incoming text/media, sent/delivered/read/failed, conversation reuse + unread, unknown contact, multi-identity, incoming + duplicate call, staff reply via provider, guest Hub access, permission denial, diagnostics secret leakage.

Existing `tests/Feature/ExampleTest.php` needs a full app DB / `APP_KEY` and was not used as a regression suite.

---

## REGRESSION RESULTS

Code review: Hub sends only go through `WaSenderProvider` → `BeyondWasenderService::sendTextRaw`. Internship / OTP / quotation / booking / announcement / letter paths still use `NotificationRouter` / existing services.

Live send regression was **not** executed against production in this phase (no VPS deploy, no change to production `.env`). After deploy, manually confirm:

- internship task WhatsApp
- internship grade/pass WhatsApp
- OTP
- quotation send
- booking/rental reminder
- announcements
- letters
- document send

---

## ENVIRONMENT VARIABLES

New / documented in `.env.example`:

```
WASENDER_WEBHOOK_SECRET=
WHATSAPP_WEBHOOK_RETENTION_DAYS=30
WHATSAPP_DEFAULT_CONVERSATION_MODE=HUMAN
WHATSAPP_SESSION_STATUS_CACHE_SECONDS=45
# QUEUE_CONNECTION=database   (recommended for webhooks)
```

Reuse existing: `WASENDER_API_KEY`, `WASENDER_SESSION_ID`, `WASENDER_BASE_URL`, `WASENDER_MIN_SEND_INTERVAL_MS`.

Do not put secrets in Hub UI. Diagnostics show `Configured` / `Missing` only.

---

## SERVER REQUIREMENTS

- PHP 7.4 + existing Laravel 6 stack
- `jobs` and `failed_jobs` tables (already in repo migrations)
- A queue worker if `QUEUE_CONNECTION=database`
- WaSender webhook pointed at `https://beyondtechworld.com/api/webhooks/wasender` (or the live host)

If the queue stays `sync`, the job still runs in-process after persist. A worker is strongly recommended so the HTTP request can return 200 without depending on send/parse time.

---

## WASENDER CONFIGURATION REQUIRED

In the WaSender session:

1. Webhook URL: `https://<host>/api/webhooks/wasender`
2. Webhook secret: same value as `WASENDER_WEBHOOK_SECRET`
3. Subscribe: `messages.received`, `messages.update`, `message-receipt.update`, `call`

---

## PRODUCTION DEPLOYMENT CHECKLIST

Do this manually on the VPS when you choose to deploy. This phase did not change production `.env` or start a worker.

1. Deploy code (`git pull` + existing Laravel deploy script).
2. `php artisan migrate --force`  
   (runs `2026_09_22_180000_create_whatsapp_hub_foundation`).
3. Add to production `.env` (do not commit):

   ```
   WASENDER_WEBHOOK_SECRET=<long random secret>
   WHATSAPP_WEBHOOK_RETENTION_DAYS=30
   WHATSAPP_DEFAULT_CONVERSATION_MODE=HUMAN
   QUEUE_CONNECTION=database
   ```

4. Confirm `jobs` / `failed_jobs` exist (`php artisan queue:failed` should not error).
5. `php artisan config:clear && php artisan cache:clear && php artisan view:clear`
6. Reload PHP-FPM (existing deploy script).
7. Queue worker (Supervisor or systemd). Example:

   ```
   php artisan queue:work database --queue=whatsapp,default --sleep=1 --tries=3
   ```

   Keep it running with the server’s existing process manager. Do not leave only `sync` if webhook volume is non-trivial.

8. Grant WhatsApp Hub permissions to any non-admin roles that need them (roles 1–2 already get them from the migration).
9. In WaSender: set webhook URL, secret, and events listed above.
10. Smoke tests:
    - Send a WhatsApp text to the business number → appears in Conversations
    - Reply from Hub → phone receives it; row is SENT
    - Open the chat → ticks move to delivered / read
    - Missed/incoming call → Calls screen
    - Diagnostics: session status, webhook last event, secrets show Configured
    - Existing internship/OTP/quotation WhatsApp still send

### Rollback

```
php artisan migrate:rollback --step=1
```

Then revert the deploy commit and remove the WaSender webhook URL if needed. Outbound notifications continue to work without Hub tables.

---

## KNOWN ISSUES

- Hub outbound history starts at Phase 1. Older internship/letter sends are not backfilled into `whatsapp_messages`.
- `QUEUE_CONNECTION` is still `sync` until production `.env` is updated.
- Command Center cards for tenants / appointments / AI are omitted (not in ERP yet).
- Staff Hub replies are text-only in this phase.
- Incoming media is metadata only (no local media download yet).
- Unique `provider_message_id` is nullable; empty provider IDs are stored as null.

---

## DEFERRED (Phase 2+)

AI Assistant, CRM leads, rental/quotation assistant, internship WhatsApp submission, employee check-in, document retrieval/OTP, tenants, bill pay, appointments, Google Calendar, automations, management brief, advanced analytics.

---

## RECOMMENDATIONS FOR PHASE 2

- Inbox filters (unread / human / customers / students)
- Meaningful-enquiry lead capture (not greetings/spam)
- AI / HUMAN handover
- Staff document attach from existing ERP PDFs
- Optional Echo/polling for live inbox updates
- Confirm production worker is running before enabling high-volume inbound

---

## NEXT PHASE

Phase 2 — Conversations + Leads, after live validation is complete and Phase 2 is explicitly approved.

---

## PRODUCTION DEPLOY

Deployed 22 September 2026. Commit `08b1a1c`. Production HEAD matches that commit.

Completed on the VPS:

- Laravel deploy + additive migration `2026_09_22_180000_create_whatsapp_hub_foundation`
- Production `.env` now has `QUEUE_CONNECTION=database`, `WHATSAPP_WEBHOOK_RETENTION_DAYS=30`, `WHATSAPP_DEFAULT_CONVERSATION_MODE=HUMAN`, `WHATSAPP_SESSION_STATUS_CACHE_SECONDS=45`, and `WASENDER_WEBHOOK_SECRET` (value stored only in `.env`)
- Existing `WASENDER_API_KEY` / `WASENDER_SESSION_ID` were not changed
- PM2 process `beyondtechworld-whatsapp-queue` runs as `www-data`:  
  `php artisan queue:work database --queue=whatsapp,default --sleep=1 --tries=3 --timeout=90`
- Endpoint smoke (synthetic signed POST, not a real WhatsApp message): unsigned/wrong signature → `401`; valid signature → `200`; job processed; `failed_jobs = 0`
- WaSender session status via existing API key: `connected`

Not completed via API:

- WaSender dashboard webhook URL / events. Session management (`PUT /api/whatsapp-sessions/{id}`) requires a **Personal Access Token**. The production Session API Key correctly returns `401` on that endpoint and was not used to change session settings.

Remaining WaSender dashboard step (must use the same secret already stored in production `.env`):

1. Open the live BeyondTechWorld WhatsApp session → Webhooks.
2. Webhook URL: `https://beyondtechworld.com/api/webhooks/wasender`
3. Webhook Secret: paste the current production `WASENDER_WEBHOOK_SECRET` (do not generate a different one unless you also replace the `.env` value).
4. Enable: `messages.received`, `messages.upsert`, `messages.update`, `message-receipt.update`, `call`.
5. Save.

Read the secret on the VPS only (do not commit it):

```
sudo grep '^WASENDER_WEBHOOK_SECRET=' /var/www/beyondtechworld/laravel-app/.env
```

---

## LIVE VALIDATION

Prepared 22 September 2026. Production code and queue are live. Nothing below is marked PASSED until it has been tested against the real WaSender / WhatsApp environment.

Phase 1 automated suite re-run: **16 tests, 67 assertions, OK**.

Full project PHPUnit: **19 passed, 1 failed** — `Tests\Feature\ExampleTest::testBasicTest` (`GET /` expected 200, got 500). This is the stock Laravel example hitting the public home page without a full production database in the local test env. It is **not** a Phase 1 Hub regression.

### A. Send ordinary WhatsApp message to BeyondTechWorld

Expected: Message appears in Conversations.

**NOT TESTED**

### B. Verify identity resolution

Expected: If the number belongs to an ERP user/customer/employee/intern, all applicable identities appear on the contact panel.

**NOT TESTED**

### C. Send reply from WhatsApp Hub

Expected: Message arrives on the phone.

**NOT TESTED**

### D. Open/read reply on phone

Expected: Hub eventually shows delivered/read status from provider events.

**NOT TESTED**

### E. Send image/document to BeyondTechWorld

Expected: Inbound event is handled safely and message/media metadata appears correctly.

**NOT TESTED**

### F. Call the BeyondTechWorld WhatsApp number

Expected: Call appears under Calls.

**NOT TESTED**

### G. Send multiple messages quickly

Expected: No duplicate conversations/messages and no webhook duplication.

**NOT TESTED**

### H. Test existing internship WhatsApp notification

Expected: Existing workflow still works.

**NOT TESTED**

### I. Test OTP

Expected: Existing OTP workflow still works.

**NOT TESTED**

### J. Test an existing quotation/rental WhatsApp message

Expected: Existing workflow still works.

**NOT TESTED**

### K. Restart the queue worker

Expected: Pending webhook jobs continue processing normally.

**NOT TESTED**
