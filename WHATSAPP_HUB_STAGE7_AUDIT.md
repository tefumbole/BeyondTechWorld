# WhatsApp Hub — Stage 7 audit

Audit date: 23 September 2026. Scope is the BeyondTechWorld Laravel ERP after Stages 1–6. Only document types with a real ERP record and a real generator are available on WhatsApp.

## Security rule

A WhatsApp number identifies a person. It does not authorize every document. Retrieval requires identity, ownership of that exact record, the catalogue sensitivity, and a verification session when the catalogue says so. The assistant classifies the request. `DocumentAuthorizationService` decides ownership. `WhatsAppVerificationService` decides the OTP. Existing controllers generate the file. `WhatsAppProviderInterface` sends it.

## Catalogue

| Document | Exists | Generator to reuse | Storage | Owner | Sensitivity | OTP | WhatsApp |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Customer quotation | Yes. `quotations` | `QuotationController::buildQuotationPdf` (view `pdf.quotation_pdf`) | `storage/app/public/quotation/quotation_{id}_invoice.pdf` | `quotations.customer_id` | VERIFIED | Yes | Yes |
| Customer invoice | Yes. `sales` is the sales invoice, separate from a quotation | `SaleController::buildSaleInvoicePdfBinary` (view `pdf.sale_pdf`) | Temporary copy under `storage/app/whatsapp-documents/` | `sales.customer_id` | VERIFIED | Yes | Yes |
| Customer receipt | Payment rows exist on `payments` linked to a sale. No receipt PDF generator | None | None | Sale’s customer, only if a `payments` row exists | VERIFIED | Would be required | No. A payment claim does not create a receipt |
| Customer contract | `contracts` (`BtwContract`) and booking/event contracts exist. Party ownership is not a reliable employee/customer foreign key for WhatsApp | `ContractPdfService::generateFinal` exists but is not wired here | Contract storage used by the contracts module | Not provable from the WhatsApp identity | VERIFIED | — | No |
| Employee payslip | `hr_payslips` exists. `PayslipService::verify` is a public code lookup and returns net pay. It is not a sender | No payslip PDF view or generator | None | Payroll staff profile, not used for WhatsApp send | VERIFIED | — | No. No file is invented |
| Employee contract | No employment-contract file tied to `employees.id` | None safe to reuse. Letter PDFs overwrite one shared `letter.pdf` path | — | — | VERIFIED | — | No |
| Employee timesheet | `be_timesheet_entries` exist from Stage 6 | No timesheet PDF | — | The employee/intern user | VERIFIED | — | No |
| Mission order | No mission-order table or PDF | None | — | — | VERIFIED | — | No |
| Internship letter | Applicant `internship_letter_path` is an uploaded school letter, not a letter this ERP issues | None | Applicant upload | Applicant | VERIFIED | — | No |
| Internship assessment | No assessment PDF for an enrolment | None | — | — | VERIFIED | — | No |
| Internship certificate | `course_certificates` belong to course registrations, not internship completion. No internship certificate generator | Course certificate creation stays in `CourseManagerService` and is not called from WhatsApp | — | Course registration | VERIFIED | — | No. A request does not make someone eligible |

Public company text stays on the existing knowledge tools. It is not a generated file.

## OTP already in the ERP

Portal login OTP, staff phone OTP, cart OTP, and task-invite OTP are session or login flows. They are not purpose-scoped, hashed document challenges. Stage 7 does not reuse them.

Stage 7 stores `whatsapp_verification_challenges.otp_hash` (HMAC-SHA256 with the app key) and `whatsapp_verification_sessions`. The plaintext code is handed to the WhatsApp provider and is not written to the challenge, the assistant activity preview, or conversation memory.

## Sending path

`WhatsAppConversationService::sendExistingDocument` already rejects paths outside the application root. Stage 7 only passes files returned by the quotation or sale generators (or a fixture under `storage/app/whatsapp-documents` in tests). User text is never used as a filesystem path.

`list_available_documents` previously listed quotation filenames from a public directory. It now returns catalogue labels the resolved identity is allowed to ask for.

## Routing

Inbound handling stays: webhook idempotency, then human handover, then attendance commands such as CHECK OUT, then a 6-digit code only when a verification challenge is pending, then the document request, then other assistant intents. A pending code must not consume CHECK OUT or HUMAN.

## Stage 8

Not started. A property/tenant module was not part of this audit. Stage 8 has to audit that module before any tenant WhatsApp flow.
