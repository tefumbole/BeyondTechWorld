# Beyond Cloud — Phase 1F audit

Audit date: 2026-10-02. Production is `f8c9e0be95ff635512e8650617a3a34732d29cf5`. This audit does not change production. Public signup is closed there. Payment webhooks return HTTP 501.

## What already exists

| Piece | Where | Reuse |
| --- | --- | --- |
| `/subscriptions` | `BeyondController@subscriptions`, `beyond/subscriptions.blade.php` | Reads `cloud_plans`. Add the Build Your Own Company link. Do not hardcode prices. |
| `/cloud/register` | `CloudPortalController` | Closed unless `config('cloud.public_onboarding')` is true. Default is false. Keep that switch. |
| `CloudTenant` | `cloud_tenants` | Name, legal name, system name, slug, phone, email, country, city, address, currency, timezone, logo_path. Type CUSTOMER must be set explicitly. Default status without an explicit value is PENDING, so onboarding must set ACTIVE. |
| `CloudTenantMembership` | memberships | OWNER is `membership_role`, not `users.role_id`. |
| `CloudPlan` / `CloudModule` | catalog | Messaging 5,000 XAF, Sales 5,000, Rentals 5,000, WhatsApp Hub 10,000. Trial 24 HOUR on each row. |
| `CloudSubscriptionService::startTrial` | Phase 1E | Only place that may start a trial. Enforces one introductory trial per company and module, and one trial per phone and module. Refuses INTERNAL. |
| `CloudModuleAccessService` | Phase 1E | Module gates. Company suspension is not yet a separate check from subscription SUSPENDED. |
| `CloudTenantContext` | Phase 1D | Guests still resolve to BeyondTechWorld when the legacy switch is on. A public `/c/{slug}` page must not use that context to list products. |
| Users | `users`, web guard | One account per email. `name`, `email`, `phone`, `password`, `role_id`. No `MustVerifyEmail`. New portal users are role_id 5, which is not platform admin (role_id 1 or 2). |
| Company settings | `CloudTenantSetting`, portal settings page | business_summary, services, business_rules. Hero upload already checks type, size, and real image bytes. |
| Logo column | `cloud_tenants.logo_path` | Column exists. No tenant branding directory yet. Hero files live under `storage/app/tenants/{uuid}/`. |
| Company switch | `POST /cloud/company` | Membership is required. Session carts are cleared. The request id is not trusted without a membership. |
| Landing | public Beyond pages | No per-company `/c/{slug}` route. `CloudSlug` was written for that route and is not reserved-slug aware. |
| Biller / general settings | installation-wide | Invoice header for Beyond. Do not copy that table per customer. Customer identity stays on `CloudTenant`. |
| WhatsApp | one Wasender session in `config/services.php` (`WASENDER_API_KEY`, `WASENDER_SESSION_ID`). `cloud_whatsapp_connections` maps that session to BeyondTechWorld. | Do not hand this session to a new company. See the provider audit. |
| Mail | Laravel mail | PHPUnit uses the array driver. No separate verification product. |
| Abuse controls | CSRF, `throttle` middleware exists but is not on register | Add throttle. No captcha package is installed. |

## What is missing

- Module choice, price summary, and “due today 0” on the public form.
- First name and last name, company address, currency, and timezone on that form.
- A transaction that creates the user, CUSTOMER tenant, owner membership, selected trials, and base settings together.
- Reserved slugs.
- Existing-email login instead of a second user.
- A second company for someone who is already signed in.
- `/c/{slug}` that does not grant admin rights.
- Dashboard checklist, trial countdown, and truthful “payment is not available yet” wording.
- Logo storage under `cloud-tenants/{uuid}/branding/`.
- Company suspend/reactivate distinct from a module expiry.
- Registration rate limit and a one-time submit token.

## Decisions

- Keep `CLOUD_PUBLIC_ONBOARDING` default false. Tests turn it on. This phase is not deployed, so production signup stays closed.
- Public module choices are Messaging, Sales & Invoices, and Rentals. WhatsApp Hub stays a catalog plan and is not a public checkbox.
- No card, MoMo, or Stripe step. Pay buttons on the portal stay hidden until `CLOUD_PAYMENTS_LIVE` is turned on. Default is false.
- Customer WhatsApp self-connect stays “admin setup required”.
- No SMS. No sample records unless the owner presses Add sample data.
- No destructive delete-company action.
