# Stage 9 — Appointments and Google Calendar

Date: 25 September 2026

WhatsApp books through the ERP appointment record. Google Calendar is a separate channel and stays disconnected until credentials are set. Stage 10 was not started. This was not deployed.

## What changed

- `appointments` stores the purpose, category, contact, optional customer, staff, location, time, status, and Google event id.
- Weekly windows live in `appointment_availability`. If a day has no window, the assistant says so and does not invent a time.
- The customer must reply with a slot number. Only then is the row created, with a reference such as `APT-0001`.
- `CalendarService` calls `GoogleCalendarProvider`. A missing credential leaves `google_sync_status` as `NOT_CONFIGURED` and the booking still stands.
- A signed Google channel notification can update or cancel the matching ERP row. A wrong token is rejected.
- `CONFIRM`, `RESCHEDULE`, and `CANCEL` apply only to an upcoming appointment on that same chat.
- Reminders use the configured intervals, defaulting to 24 hours. The scheduler stays off until `APPOINTMENT_REMINDERS` is enabled. A sent interval is not sent again.
- Hub screen: WhatsApp Hub → Appointments. Permission: `whatsapp.appointments` for roles 1 and 2.

## Tests

`./vendor/bin/phpunit --filter WhatsApp` — 160 tests, 898 assertions, OK.

## Not done

- No production calendar was connected.
- No availability window was seeded.
- Live WhatsApp booking was not run.
- Stage 10 has not started.
