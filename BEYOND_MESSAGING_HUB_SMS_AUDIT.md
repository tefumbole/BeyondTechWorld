# Beyond Messaging Hub — SMS audit

Audit date: 2026-10-02. No provider account was changed. Phase 1F was not deployed. Production remains `f8c9e0be95ff635512e8650617a3a34732d29cf5`.

## What already sends messages

WhatsApp is the live channel. The send stack is:

| Piece | Role |
| --- | --- |
| `WhatsAppProviderInterface` | Provider contract: text, document, image, session, groups. Bound to `WaSenderProvider` in `AppServiceProvider`. |
| `WaSenderProvider` | The only Wasender implementation. Do not add a second one. |
| `BeyondWasenderService` | Installation Wasender client. `sendText()` goes through `NotificationRouter`. `sendTextRaw()` is the direct Wasender call and already blocks CUSTOMER tenants that lack messaging write. |
| `NotificationRouter` | Central WhatsApp router today: Wasender by default, Twilio Content templates when `WHATSAPP_SERVICE=TWILIO`. |
| `TwilioWhatsAppService` | Alternate WhatsApp transport, not SMS credits. |
| `CloudWhatsAppConnection` | One row per company connection. The secret stays in config (`credentials_reference` is a key name). Production has one ACTIVE connection for the INTERNAL BeyondTechWorld tenant. |
| Webhooks | `WaSenderWebhookController` and `ProcessWasenderWebhook`. Tenant comes from the connection, not the sender phone. |
| Hub data | `whatsapp_contacts`, `whatsapp_conversations`, `whatsapp_messages`, delivery fields on messages, `WhatsAppHubController`. |
| Phone shape | `WhatsAppPhone::normalize` / `sanitizeForStorage`. Digits with country code. Cameroon local numbers use 237. |

`NotificationRouter::sendSms()` is an older installation gateway (Twilio or Clickatell from `config/services.php`). It is not tenant-scoped, it does not keep a credit ledger, and it is not the Messaging Hub. This phase leaves that method in place so existing settings screens keep their current behavior. New company SMS does not call it.

## Call sites that still know the channel

These call `NotificationRouter` or `BeyondWasenderService` directly. They should eventually ask the hub to “send this notification.” This phase does not reroute them, so current WhatsApp behavior stays.

- Login and home OTP (`LoginController`, `HomeController`, `PhoneOtpLoginController`)
- Letters and quotation links (`LetterController`)
- Announcements (`AnnouncementController`, `AnnouncementNotificationService`)
- Internship notices and documents (`InternshipProgramService`, `InternshipWhatsAppService`)
- Attendance (`AttendanceWhatsAppService`)
- Rentals and booking invoice command (`WhatsAppRentalController`, `SendBookingInvoiceWhatsApp`)
- Property notices (`PropertyWhatsAppService`)
- Contracts (`ContractWorkflowService`, reminder commands)
- Applications and staff notices (`ApplicationNotifier`, `StaffPermissionNotifier`)
- Settings test send (`SettingController`)
- Base `Controller` helper
- Conversation replies, group replies (`WhatsAppConversationService`, group services)
- Document delivery (`WhatsAppDocumentService`)

Subscription and trial notices are rows in `cloud_subscription_notices`. They are not sent through a customer’s WhatsApp entitlement.

## Entitlement and tenancy

`CloudModuleAccessService` treats messaging as WhatsApp Hub or the Messaging plan. INTERNAL BeyondTechWorld is allowed by platform entitlement and does not need a subscription. CUSTOMER companies need a writable messaging trial or subscription. `CloudTenantContext` is cleared in `finally` on the web middleware and on queued WhatsApp jobs.

The 5,000 XAF Messaging plan is the module subscription. Nothing in the catalog says that price includes unlimited SMS.

## What is missing

- A channel-neutral notification with a stable id and per-channel attempts
- An SMS provider interface and registry that does not call Orange, MTN, or Infobip
- A tenant SMS connection whose secret is only a reference
- An append-only segment ledger with reservation
- Configurable segment price rows (none seeded)
- Tenant templates with allowlisted variables
- Marketing opt-out separate from transactional notices
- A Messaging Hub screen that can show WhatsApp and SMS without a second application

## Decision

Extend `NotificationRouter` with a hub. Keep Wasender on the existing interface. Ship `FakeSmsProvider` for tests. Real provider classes stay unactivated until a documented adapter exists.
