# WhatsApp Hub — Stage 8 Audit

Date: 23 September 2026  
Codebase: BeyondTechWorld Laravel ERP after Stages 1–7 (`90e35f2` and the Stage 8 working tree).

WhatsApp remains an interface. It does not become a property database.

## Decision

A property/tenant module does **not** exist.

Stage 8 therefore builds the minimum ERP foundation first:

Property → Unit → Tenancy (linked to an existing customer) → Rent obligation → Staff-recorded payment → Receipt → Maintenance request.

There is no `whatsapp_tenants` table. Conversation and request metadata may point at those ERP rows. The rows themselves are the authority.

Equipment rental stays on Product → Booking. `pdf/rent_pdf.blade.php`, shop-rent, studio rental contracts, and WhatsApp `RentalRequest` are Stage 4 equipment rental. They are not reused for tenancies.

## What was searched

| Area | Result |
| --- | --- |
| Property, building, apartment, unit, room, tenant, tenancy, lease, landlord | No ERP models or tables. `BookingCategoryHelper` only buckets a product name that contains "APARTMENT" or "ACCOMMOD". |
| Rent invoice, rent receipt, rental agreement | Equipment/booking rental documents only. No tenant receipt generator. |
| Maintenance request | `CheckForMaintenanceMode` is Laravel downtime, not a work order. |
| Utility account | None. |
| Customer | `customers` is the person record. A tenant will point at `customer_id`. A customer is not a tenant until a tenancy exists. |
| Employee | Existing `employees` rows are the staff who can be assigned. No technician table. |
| Invoices and sales payments | `sales` invoices and the `payments` table serve POS/sales. They are not tenant rent. |
| Receipt PDF | No tenant or sales-receipt PDF that Stage 7 can send. `CUSTOMER_RECEIPT` stays unavailable. |
| Stage 7 | `WhatsAppDocumentRegistry`, `DocumentAuthorizationService`, `WhatsAppVerificationService`, verification sessions, and `sendExistingDocument` are the document path. Stage 8 extends that registry. It does not add a second OTP. |
| Stage 3 tools | `AssistantToolRegistry` / `AssistantToolExecutor`. New tools are named methods on the same executor. |
| Stage 2 leads | Unchanged. Property work does not write leads. |
| Stage 5 media | Internship file guards exist for submissions. Property photos and voice notes get their own validator with the same checks: MIME, extension, size, filename, ownership. Files are stored, not executed. |
| Stage 6 attendance | `AttendanceWhatsAppService` remains the only check-in. A maintenance row may store an attendance id. Attendance does not close the request. |
| Notifications / WaSender | Existing `WhatsAppProviderInterface` and conversation send methods. No new provider. |
| PawaPay | Config and callback URLs only. No PHP client, and the sample callbacks are not a BeyondTechWorld bill-pay module. Stage 8 does not call PawaPay. |
| Campay / MTN MoMo | Config only, used outside this module. Not called from WhatsApp. |
| Stripe | Charges inside sale, booking, and funeral pledge code. Not a reusable bill-payment service. Stage 8 does not charge cards. |
| Funeral pledges | Separate MoMo/Orange/Stripe checkout. Not tenant bills. |

## Payment boundary

`BillPaymentRequest` is a request. WhatsApp text, a screenshot, or an AI reading of that screenshot cannot mark it paid, reduce rent, or create a receipt.

There is no approved BeyondTechWorld bill-pay client to debit a wallet, MoMo number, or card. Confirmation moves a complete request to `UNDER_REVIEW`. `PAID` is set only by a signed webhook (or the same service method the webhook calls) with a verified event id. A timeout becomes `PENDING_CONFIRMATION`. Reconciliation reads stored provider events. It does not start another debit, because this module has no debit method.

Live charges against real money are out of scope. Sandbox success means the signed webhook path. It is not a production payment pass.

## Documents

Registered on the Stage 7 registry, still behind the existing OTP and ownership check:

| Key | Sent only when |
| --- | --- |
| `RENT_RECEIPT` | A real `property_rent_payments` row exists for that tenancy. |
| `RENT_STATEMENT` | The tenancy exists. Figures come from obligations and payments. |
| `TENANCY_AGREEMENT` | Staff have stored an agreement file. No file is generated to look signed. |
| `BILL_PAYMENT_RECEIPT` | The bill request is `PAID`. |

`CUSTOMER_RECEIPT` stays unavailable so a sales receipt is not invented.

## Routing

Explicit attendance commands, including `CHECK OUT`, stay ahead of pending maintenance, bill confirmation, and tenant clarification. A pending Stage 8 workflow must not consume them.

## Stage 9

Not started. Appointments and Google Calendar stay out of this stage.
