# WhatsApp Hub — Stage 9 Audit

Date: 25 September 2026

WhatsApp remains an interface. An appointment is an ERP record. Google Calendar is a scheduling channel, and it stays disconnected until credentials are configured.

## What already exists

- The assistant recognizes `appointment`, `schedule a meeting`, and `book a meeting`, then has nowhere to write a row.
- Permission `whatsapp.appointments` was named in the Phase 0 audit and was not seeded.
- There is no `google/apiclient`, no calendar webhook, and no appointment table.
- The events calendar in the ERP is an internal events stub. It is not a customer appointment book and it is not Google Calendar.

## Decision

Stage 9 adds `appointments` as the business record. Availability comes only from staff-configured windows. The assistant offers those windows and creates a row only after the customer chooses one. It does not invent a time.

`CalendarService` talks to `GoogleCalendarProvider`. Controllers do not call Google. When the calendar is not configured, the ERP appointment is still saved and the reply says the calendar was not updated.
