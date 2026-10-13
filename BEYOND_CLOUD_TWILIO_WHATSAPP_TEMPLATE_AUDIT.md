# Beyond Cloud — Twilio WhatsApp template and service mapping

Date: 2026-10-03. Read-only. No messages were sent. WaSender was not changed. MAI Voice was not turned on. WhatsApp Calling was not enabled. Credentials are not included.

## Account and sender

Twilio authentication succeeded. The account status is `active`, type `Full`.

WhatsApp senders on the account:

| Sender (last four) | Status | WABA | Voice application | Messaging limit | Quality |
| --- | --- | --- | --- | --- | --- |
| 4183 | ONLINE | yes | no | 250 customers / 24 hours | HIGH |
| 8886 | OFFLINE | yes | no | unavailable | UNKNOWN |
| 6233 | OFFLINE | yes | no | unavailable | UNKNOWN |

The online sender is the one already stored in local project environment files as the WhatsApp `From`. BeyondTechWorld production text still goes through WaSender. This audit does not point that number at Twilio.

WhatsApp Business Calling stays off because no voice application is attached.

## How templates are sent today

`TwilioWhatsAppService::sendContentTemplate()` already sends with `contentSid` and `contentVariables`. Free-form `body` text is a separate method and only works inside an open WhatsApp session. New business-initiated WhatsApp messages should use an approved Content SID, not a hardcoded body.

The live Beyond hub sends through WaSender. That path was not switched.

## Templates on the account

The Content API returned 20 templates. Approval was read from each template’s WhatsApp approval request. There were no pending and no rejected templates.

Three Content SIDs named in an earlier note are not on this account. Twilio returned HTTP 404 for `otp_message`, `booking_completed`, and `test_message`. They are not mapped below.

| Friendly name | Content SID | Language | WhatsApp status | Category | Variables |
| --- | --- | --- | --- | --- | --- |
| beyond_notice | HX47150e179fdbab79738d060fb0ac6415 | en | approved | UTILITY | 1 headline, 2 name, 3 main message, 4 reference, 5 extra |
| copy_client_notification | HX06e0cea9a7573212a390faf561a15a67 | en | approved | UTILITY | 1 program, 2 department, 3 academic year |
| booking_received | HX017d3c768407e7bcb3f23422a5c6f9c7 | en | approved | MARKETING | 1 name, 2 booking reference, 3 status |
| faby_template_for_announcement | HX65bf1f493ce0769926ab94ada256721a | en_US | approved | MARKETING | 1 details |
| notifications_welcome_template | HX80feff0f485668fa01bf6e92c58cbac8 | en | unsubmitted | — | first_name |
| notifications_order_update_template | HXdf3f062feb79ea727f0c6b3f15b6c42e | en | unsubmitted | — | date, time |
| notifications_order_update_template | HXdf0682b0b68e2eded502cf3f84225dff | en | unsubmitted | — | first_name, order_number |
| notifications_appointment_confirmation_template | HX98be234e0485f50d7714404e38b65095 | en | unsubmitted | — | first_name, date, time |
| message_opt_in | HXefe74800771df2f0ed3e4e11f976e584 | en | unsubmitted | — | none |
| notification_order_tracking | HX19c3796f69c746e6ea470a67eea7a3c3 | en | unsubmitted | — | order_number, date |
| sample_issue_resolution | HXa99463af599c1995130c757c40e0d40c | en_US | approved | UTILITY | 1 |
| sample_issue_resolution | HXf95284217e19afd88bff5592e66f840d | id | approved | MARKETING | 1 |
| sample_happy_hour_announcement | HX079fbfb14758ec5b67b8950f9da252a7 | id | approved | MARKETING | 1 place, 2 time |
| sample_purchase_feedback | HX96418978bd9f728f26b11248a03ec2af | pt_BR | approved | MARKETING | 1 |
| sample_movie_ticket_confirmation | HXf6a177d015de38063552d8e7e3cc1b17 | en_US | approved | UTILITY | 1–4 ticket fields |
| sample_movie_ticket_confirmation | HX3fbfc30292dc2585b38ecbd026b3cf8d | es | approved | UTILITY | 1–4 |
| sample_flight_confirmation | HX6c3c0d14f80d90b4ed3bb4dec9f5608e | es | approved | UTILITY | 1–3 |
| sample_flight_confirmation | HX3d0f270d2fdb88206c36a6c43b48dfc3 | id | approved | UTILITY | 1–3 |
| sample_shipping_confirmation | HX371490e6a5d4dc1b74c4c3179d50c830 | es | approved | UTILITY | 1 |
| sample_shipping_confirmation | HX00dd790e61e6166000ac969568439f96 | id | approved | UTILITY | 1 |

Approved body text that matters for mapping:

- `beyond_notice` is fixed around “Congratulations”, then `{{1}}`, “Hello `{{2}}`”, `{{3}}`, “Reference: `{{4}}`”, “Details: `{{5}}`”, and “Beyond Company Ltd.”
- `copy_client_notification` says the application was received, the person was admitted to `{{2}}` for the `{{3}}` academic year, and an admission letter is attached.
- `booking_received` names Manukeza Booking & Tours, not Beyond.
- `faby_template_for_announcement` is an announcement with one free-text detail.
- The English `sample_issue_resolution` card asks whether an issue was solved and says the message is from an unverified business.
- The other `sample_*` templates are flights, movie tickets, shipping, happy hour, or purchase feedback, in English, Spanish, Indonesian, or Portuguese, and several also say the business is unverified.

Counts: approved 14, unsubmitted 6, pending 0, rejected 0. Of the approved set, Utility is 9 and Marketing is 5. Authentication is 0. Unsubmitted templates are not eligible to send as business-initiated WhatsApp until Meta approves them.

## ERP events that already exist

Beyond already builds these WhatsApp texts in `App\Support\WhatsAppMessage`. They go out through WaSender as session or staff messages. They are the events a Twilio template would have to match. No new module was added.

Customers and sales: sale confirmation, delivery signature request, delivery signed notice.

Quotations: approval request, quote submitted, signed and unsigned PDF notices, staff and CC copies.

Rentals and bookings: booking confirmation, scheduled reminder, late return, goods-received signature, signed booking copy.

Events: event contract signature request.

Internship and jobs: application under review, selected, rejected, documents requested, agreement signed, supervisor assigned, daily task, submission received, review reminder, timesheet reminder, admission login.

Employees: permission request, work-related admin notices in the same helper.

Appointments exist as MAI tools (`create_appointment`, reminders are not a dedicated WhatsApp template in this helper).

Documents and OTP: `otpMessage` for login and verification. Document send is a file on the open chat, not a Content template.

Cloud subscriptions, maintenance, and rent reminders are product areas in the ERP. They do not have their own approved Twilio template.

MAI callback and human follow-up are voice-call records from the stub voice work. They are not a Twilio template.

## Mapping

Only approved WhatsApp templates can be sent outside an open session. “Suitable” means the fixed wording, category, and brand match a real Beyond event. A Marketing template is not treated as a Utility transaction.

| Template | Category | Status | Module | Event | Recipient | Trigger | Variables | Suitable | Reason |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| beyond_notice | UTILITY | approved | Customers / positive notices | A completed successful notice that really is congratulations | Customer | An ERP record was just created or approved and the text is good news | 1 headline, 2 Customer name, 3 short fact from that record, 4 reference number, 5 one extra field | YES, narrow | The body always says “Congratulations” and “Beyond Company Ltd.” It can cover closely related success notices. It cannot carry a reminder, a failure, a balance, or a price. |
| beyond_notice | UTILITY | approved | Sales, quotations, rentals | Payment reminder, rejection, late return, invoice chase | Customer | Those ERP events | same | NO | The fixed congratulations line would be false. |
| copy_client_notification | UTILITY | approved | Internship | Person admitted and an admission letter is actually attached | Applicant | Internship admission, not a general status change | 1 program, 2 department or track, 3 year or intake | YES, one workflow | The template says they were admitted and that the letter is attached. Send it only when both are true. |
| copy_client_notification | UTILITY | approved | Jobs | Application under review, selected without admission, or rejected | Applicant | Job or internship status | same | NO | The wording is an admission, not a status update. |
| booking_received | MARKETING | approved | Rentals / events | Booking received | Customer | Booking created | 1 name, 2 reference, 3 status | NO | The text is Manukeza Booking & Tours. Category is Marketing, so it also needs marketing opt-in. Do not reuse it for Beyond. |
| faby_template_for_announcement | MARKETING | approved | Marketing only | Consented announcement | Customer who opted in | A real announcement, not an invoice or booking | 1 announcement text | YES, marketing only | One announcement body. Not a rental, quotation, payment, or OTP. Do not send it automatically. |
| sample_issue_resolution (en_US) | UTILITY | approved | Maintenance | “Was the issue solved?” | Customer | After a maintenance job | 1 name | NO | The card says the message is from an unverified business. It is a Twilio sample, not a Beyond maintenance notice. |
| sample flight, shipping, movie, happy hour, purchase feedback | UTILITY or MARKETING | approved | none | none | — | — | sample fields | NO | Wrong business, and several are the wrong language. Happy hour and film tickets are not Beyond events. |
| notifications_appointment_confirmation_template | — | unsubmitted | Appointments | Appointment confirmation | Customer | Appointment saved | name, date, time | NO until approved | The wording fits appointments. It cannot be sent as a business-initiated WhatsApp template yet. |
| notifications_order_update_template and notification_order_tracking | — | unsubmitted | Sales | Shipment or delivery time | Customer | Sale or delivery update | order number, date, time, name | NO until approved | Wording is about shipping, which Beyond does not use for equipment rentals. Do not stretch it to a rental. |
| message_opt_in | — | unsubmitted | Consent | Ask whether the person wants messages | Customer | Before any marketing | none | NO until approved | Useful later for consent. Not a service notice. |

No approved template is an Authentication template. `otpMessage` in the ERP must not be forced into `beyond_notice` or the announcement template.

## Reuse

`beyond_notice` is the only approved Utility template that can serve more than one Beyond event, and only events that are genuinely congratulations plus a reference and a short detail. Examples that fit: a completed signup welcome, or an approval that is good news. Examples that do not fit: invoice, payment reminder, quotation chase, late return, rejection, OTP.

`copy_client_notification` serves one workflow: admission with the letter attached.

`faby_template_for_announcement` can carry different consented announcements. It must not carry unrelated operational content.

`booking_received` must not be reused for Beyond rentals or events.

## Gaps

These ERP events have no suitable approved template. Nothing was submitted to Meta.

| Missing template | Category | Module | Purpose | Variables and source |
| --- | --- | --- | --- | --- |
| beyond_service_update | UTILITY | Sales, quotations, rentals, events | Status that is not “congratulations” | name from Customer, reference from Sale, Quotation, or Booking, status from that record, one detail line from that record |
| beyond_quotation_ready | UTILITY | Quotations | Quotation is ready to review | name from Customer, quotation number from Quotation, total from the quotation total |
| beyond_rental_confirmation | UTILITY | Rentals | Booking confirmed | name from Customer, booking reference from Booking, date from the booking, status from the booking |
| beyond_rental_reminder | UTILITY | Rentals | Pickup, return, or late return | name, booking reference, due date, item name from the booking lines |
| beyond_event_reminder | UTILITY | Events | Setup or event reminder | name, event name, date, place from the event record |
| beyond_appointment_reminder | UTILITY | Appointments | Confirmation or reminder | name, date, time from the appointment record |
| beyond_payment_receipt | UTILITY | Sales | Payment recorded | name, sale or reference number, amount from the payment record |
| beyond_otp | AUTHENTICATION | Login and document verification | One-time code | code from the existing OTP issuer only |

Callback confirmation can use `beyond_service_update` later. It does not need its own template. Subscription and maintenance notices can use that same status template when those records exist. Do not add a template per screen.

## Consent, quality, and who may be messaged

Utility messages are for a transaction the person already has with Beyond: their quotation, booking, sale, appointment, or admission. Marketing messages need a separate opt-in. The announcement template and `booking_received` are Marketing. Authentication is only for a real OTP.

Do not send to a purchased list, to every customer, or toward a target of 2,000 conversations. The online sender’s limit is 250 customers per 24 hours and its quality is HIGH. Template misuse, the wrong company name, or the sample “unverified business” cards can bring that quality down. Duplicate sends need an idempotency key on the ERP event and the recipient before any sender is switched on.

This mapping is for the BeyondTechWorld internal company only. Customer tenants do not get these templates.

## First workflows, if a later phase is allowed to send

1. Do not send `booking_received` for Beyond.
2. Do not send sample flight, ticket, shipping, or happy-hour templates.
3. Use `copy_client_notification` only when an internship admission letter is really attached.
4. Use `beyond_notice` only when the congratulations line is true.
5. Submit the Utility templates in the gap list before rental, quotation, payment, or appointment notices go out through Twilio.
6. Keep WaSender as the production text path until that library is approved and a single event is chosen.

No bulk send was started.

## Final summary

```
TWILIO ACCOUNT:
ACTIVE

WHATSAPP SENDER:
ONLINE

CURRENT MESSAGING LIMIT:
250 customers / 24 hours

CONTENT TEMPLATES FOUND:
20

WHATSAPP APPROVED:
14

UTILITY:
9

MARKETING:
5

AUTHENTICATION:
0

ERP WORKFLOWS MAPPED:
11

TEMPLATES REUSABLE:
2

TEMPLATE GAPS:
8

BULK MESSAGING:
NOT STARTED

WASENDER:
UNCHANGED

MAI VOICE:
OFF

WHATSAPP CALLING:
NOT ENABLED
```

The two reusable templates are `beyond_notice` for closely related success notices, and `faby_template_for_announcement` for consented announcements only. `copy_client_notification` is usable for one admission workflow, not as a general template.
