# WhatsApp Hub — Stage 6 audit

Date: 23 September 2026

WhatsApp check-in uses the attendance and timesheet records the ERP already has. It does not create a second attendance or payroll system. Stage 7 (document OTP) is out of scope.

## Reused

| Need | Existing record | How Stage 6 uses it |
| --- | --- | --- |
| Employee identity | `employees` via `WhatsAppIdentityService` role `employee` | Phone match only. Unknown numbers do not write attendance. |
| Intern identity | `internship_enrolments.status = active` and role `intern` | Same rule as Stage 5. Check-in does not complete an internship task. |
| Office attendance | `attendances` (`date`, `employee_id`, `user_id`, `checkin`, `checkout`, `status`, `note`) | One row per person per day. Empty `checkout` is an open session. `status` stays the existing on-time flag against `hrm_settings.checkin` (1 on time, 0 late). |
| Expected office times | `hrm_settings.checkin` / `checkout` | Late/early flags only. No penalty and no payroll change. |
| Intern schedule | `be_working_week` (`monday`…`sunday`, `*_start`, `*_end`, `lunch_break_minutes`, `expected_hours_per_day`) | Read per user. Not hardcoded. |
| Timesheet hours | `be_timesheet_entries` through `TimesheetService::refreshDayBalance` | Checkout writes the ERP duration into `hours`. Overtime stays `overtime_pending` for a supervisor. It is not marked payable. |
| Field assignment | `event_assignments` + `event_worker_profiles` + `btw_events` | Worker must be linked by `user_id` or telephone. Setup, event, and dismantling times stay on the event. Check-out does not complete the event or the rental. |
| Field hours | `event_timesheets` / `event_timesheet_entries` via `EventTimesheetService` | Hours added on field check-out. Assignment `attendance_status` becomes `checked_in` or `checked_out`. |
| Payroll | `payrolls` | Not written. |
| Breaks | none | Not implemented. |
| Geofence coordinates | `btw_events` had venue text only | No coordinates are invented. Verification is `UNVERIFIED` until a venue latitude and longitude exist. |

`attendances.user_id` remains the ERP user the row belongs to (the employee’s user, or the intern). `employee_id` stays required for employees. Interns with no employee row use `intern_user_id` and a null `employee_id`.

## Added, because the current tables cannot store it

- Nullable columns on `attendances`: `source`, WhatsApp ids, location, distance, `location_status`, `event_assignment_id`, `intern_user_id`. `checkout` may be null while the session is open.
- Nullable `latitude`, `longitude`, `geofence_radius_meters` on `btw_events` so a real venue can be checked later. Stage 6 does not fill them.
- `attendance_correction_requests` — there is no correction workflow. Status `PENDING`, `APPROVED`, `REJECTED`. The assistant can only create `PENDING`. A staff member with `whatsapp.attendance.corrections` supplies the corrected time.
- `whatsapp_attendance_activities` — audit only. Not a timesheet.

## Rules that stay outside the model

- Server time is the check-in and check-out time. A clock time typed in the message is ignored.
- Location is taken only from a WhatsApp location message for that check-in, and only if it is newer than `WHATSAPP_ATTENDANCE_LOCATION_MAX_AGE_MINUTES` (default 15).
- Office `CHECK IN` does not require a location. Field job check-in does, unless `WHATSAPP_ATTENDANCE_FIELD_LOCATION_REQUIRED` is turned off.
- Outside the radius: attendance is still saved as `LOCATION_REVIEW_REQUIRED`. It is not described as verified.
- An open session from a previous day blocks a new check-in (`CHECKOUT_REQUIRED`). The old checkout time is not invented.
- Location coordinates are cleared after `WHATSAPP_ATTENDANCE_LOCATION_RETENTION_DAYS` (default 90) by `whatsapp:prune-webhooks`. The attendance row remains.

## Not reused as attendance

Guest invitation check-in (`invitations.check_in`) is for event guests, not staff. It is left unchanged.
