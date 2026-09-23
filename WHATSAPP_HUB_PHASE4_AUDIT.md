# WhatsApp Hub — Phase 4 Audit

Date: 23 September 2026  
Scope: existing ERP rental, quotation, and PDF stack after Phases 1–3, plus commit `c66ddb4` (an earlier, narrower rental attempt).

**Verdict: the gaps below were the state after `c66ddb4`.** The follow-up implementation adds rental requests, staff Approve & Send, no chat booking, discount refusal, and a new quotation on revision. Live WhatsApp validation is still not done. Do not start Phase 5.

---

## Existing ERP to reuse

| Area | What exists | Do not rebuild |
|---|---|---|
| Products | `Product` — `qty`, `rent_price_per_day`, `rent_price_per_hour`, `rent_price_per_month`, `is_active`, `type`, category | a second catalogue |
| Warehouse stock | `Product_Warehouse.qty`. Completing a booking (`booking_status = 1`) deducts `Product.qty`. Return (`booking_status = 3`) adds it back | a WhatsApp stock table |
| Categories | `Category` | AI-invented equipment types |
| Bookings | `Booking`, `BookingProduct` (`start`, `end`, `qty`) | a parallel reservation |
| Contracts | `BookingContract` | a WhatsApp contract |
| Quotations | `Quotation` + `ProductQuotation` lines. Status integers must stay: 1 Draft, 2 Awaiting Client Signature, 3 Approved, 4 Rejected, 5 No Signature Required, 6 Client Quote | `ai_quotations` / `whatsapp_quotations` |
| Client counter-quote | `QuotationQuote` + `QuotationQuoteLine` — this is a **client counter-offer**, not the quotation itself | using those tables as the quote lines |
| PDF | `QuotationController::buildQuotationPdf()` → `pdf.quotation_pdf` | a second template |
| Approval | `Quotation::approvalUrl()` / `rotateApprovalToken()` — public token link, not an admin URL | a WhatsApp-only accept button that books equipment |
| Send | `WhatsAppProviderInterface` → `WaSenderProvider` → `BeyondWasenderService`. Hub `sendExistingDocument()` is path-jailed | a new sender |
| Leads | Phase 2 `WhatsAppLeadService::convert()` / `findExistingCustomer()` | a second customer match |
| Reminders | `bookings:send-reminders` and `RentalReturnReminderCron` | rebuilding them in Phase 4 |

## Which booking statuses hold equipment

| Status | Meaning in this ERP | Holds a future date? |
|---|---|---|
| 1 Completed | Stock already deducted from `products.qty` | Already out of on-hand qty. Add it back only if that line’s end is on or before the requested start (it returns in time). |
| 2 Pending | Date is reserved. Qty is not deducted yet | Yes. Overlapping pending lines reduce availability. |
| 3 Return | Qty restored | No |
| 4 Partial Return | Some gear still out | Treat remaining unreturned qty as committed when the dates overlap |
| 5 / other Draft | Not a reservation | No. A chat must not create one of these just because the customer said “okay” |

A proposal in chat does not reserve stock. Only an existing ERP booking in status 1 or 2 does.

## What `c66ddb4` actually did

- `RentalAvailabilityService` checks on-hand qty, pending overlaps, and gear due back before the date.
- `RentalQuoteService` creates a normal `Quotation` in Draft plus `ProductQuotation` lines from `rent_price_per_day`.
- Dated questions can answer with a real quantity and rate, and they say it is not a confirmed booking.
- Missing ERP price does not invent a number.
- “I confirm the quotation” creates a **draft booking**. That conflicts with this spec: acceptance must use the existing approval link, and a discussion must not create a booking.
- Quotes at or under 500,000 try to send the PDF immediately. That conflicts with the default **AI prepares → staff approves → send**.

## Still open

- Not deployed. Not live-tested on real products, prices, or bookings (checklist A–P).
- Package pricing and customer-specific price lists are not applied. The rate used is `rent_price_per_day`, and discounts stay at zero unless staff edit the quotation.
- Partial-return quantity is not split out beyond the pending/completed rules above.
- Phase 5 was not started.

## Quotation line truth

Formal lines are `product_quotation`. `quotation_quotes` / `quotation_quote_lines` store a client’s proposed price change. A WhatsApp quotation must create `Quotation` + `ProductQuotation` only. A revision after issue should follow the existing editor or a new quotation, not overwrite a signed one.
