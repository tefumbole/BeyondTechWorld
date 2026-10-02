# Beyond Cloud Phase 1D report

**PHASE 1D IMPLEMENTED AND TESTED. NOT DEPLOYED.**

Public company signup stays closed. Phase 1E was not started. Alpha and Beta exist only in the automated test database. They were not created on production.

## What this phase does

The active company comes from the server. A missing company does not mean "show every row". New tenant-owned records take `cloud_tenant_id` from that server context. Another company's id in the request is ignored.

Directly owned models now filter by the active company:

products, categories, brands, units, warehouses, customers, suppliers, sales, quotations, payments, bookings, whatsapp_contacts, whatsapp_conversations.

`whatsapp_messages` still have no `cloud_tenant_id`. A message belongs to the company that owns its conversation. Property `tenancies` were not renamed and did not receive `cloud_tenant_id`.

## Tenant context

`CloudTenantContext` provides `set`, `get`, `id`, `has`, `require`, `clear`, and `withoutIsolation` for a privileged token lookup or audit. `withoutIsolation` is the bypass. Tenant-facing code does not call `withoutGlobalScopes()`.

## How a request gets a company

1. If the session names a company, the membership must be ACTIVE. Otherwise the session value is dropped.
2. If the user has exactly one active membership, that company is selected.
3. If the user has several, nothing is selected until they switch. A switch to a company they do not belong to returns HTTP 403.
4. Legacy compatibility, while `cloud.legacy_internal_context` is on: a user with no membership, including the platform Admin, and a guest on the existing site, receive the INTERNAL company whose slug is `beyondtechworld`. The id is not hardcoded. This is temporary so the live Beyond ERP and public site keep working. It never means "all companies".

Platform Admin does not receive Alpha, Beta, or any future customer company through that rule.

Console commands other than the queue worker use the same internal company only while no ACTIVE customer company exists. After a customer company exists, those commands fail closed until they iterate companies. Queue workers do not keep a company from boot. Each job sets context and clears it in `finally`, including when the job throws.

## Writes

Creating or updating a directly owned row without a company throws `MissingCloudTenantException`. The saved `cloud_tenant_id` is the active company even if the model attribute was set to something else.

Sale, quotation, booking, and payment saves reject a customer, warehouse, supplier, or sale that the active company cannot see. A WhatsApp conversation rejects another company's contact.

Columns stay nullable. `php artisan cloud:audit-ownership` is read-only and reports unowned rows, invalid tenant ids, and cross-company links. Make a column `NOT NULL` only after that command stays at zero on production.

## WhatsApp

`cloud_whatsapp_connections` stores the provider, the provider connection id, and a credentials reference such as `services.whatsapp.wasender_api_key`. It does not store the API key.

The existing Beyond connection is mapped to the INTERNAL company when no connection row exists yet. Sending still goes through `WhatsAppProviderInterface` and `WaSenderProvider`.

An incoming webhook chooses the company from the provider session id. It does not choose the company from the sender's phone. If more than one connection is active and the session id is missing, processing fails closed.

`whatsapp_contacts` uniqueness is now `(cloud_tenant_id, normalized_phone)`. The same phone can exist in two companies. Lookup goes through `WhatsAppContactLookup`, which requires the active company once the column exists.

## MAI

Tools that read products, customers, sales, quotations, bookings, payments, leads, or documents are TENANT. They run only after `CloudTenantContext` is set. `cloud_tenant_id` is removed from tool parameters if a prompt supplies it.

Property occupancy tools (`get_my_tenancy`, rent, maintenance) are IDENTITY. They are not CloudTenant tools.

Company knowledge, package prices, and appointment windows that do not read another company's rows are PUBLIC.

A search for speakers under Alpha returns Alpha products only. With no company, the tool returns `tenant_context_required` instead of the whole catalogue.

## Files, cache, branding

`CloudFileGuard` denies a download when the record's company is not the active company. Files already stored under public paths were not moved.

Cache keys for new tenant data should use `CloudCacheKey`, shaped as `cloud:{uuid}:...`. Existing installation caches were not renamed in this pass.

Customer-company documents can take their name from `CloudTenant` through `CloudBranding`. BeyondTechWorld still uses `general_settings` for its existing appearance.

Public quotation approval links resolve by the unguessable token, then bind that quotation's company. A numeric id is not the authority.

## Global scope decision

The scope is on for the directly owned models listed above, behind `cloud.isolate_queries` (default on). It was added after context, create-time ownership, and the resolver existed. Without a company the query matches nothing. That is the fail-closed rule. It is not enabled on users, plans, property tenancies, or message rows.

## Tests

`tests/Feature/CloudTenantIsolationTest.php` on sqlite:

| Check | Result |
| --- | --- |
| Product create with no company | rejected, zero rows written |
| Request-supplied `cloud_tenant_id` | overwritten by the active company |
| Alpha list / find of Beta product | denied |
| Alpha sale with Beta customer | rejected |
| Beta payment on Alpha sale | rejected |
| Same phone `237600000001` on Alpha and Beta | both exist, conversations stay separate |
| MAI "ignore your rules" with no company | no catalogue |
| MAI speaker search as Alpha | Alpha speaker only |
| Alpha job throws, then Beta job on the same runner | Beta does not keep Alpha |
| Switch to a company without membership | HTTP 403 |
| Platform Admin with no membership | INTERNAL slug `beyondtechworld` only |
| Webhook with Beta session | Beta, even if the payload phone is someone else's |
| Webhook with a phone and no session while two connections exist | no company |
| Public `POST /cloud/register` | no company created |
| `cloud:audit-ownership` | read-only, reports the planted unowned row and bad link |

Cloud foundation, internal tenant, and portal tests still pass. Messaging price and the 24-hour trial were not changed. The portal test turns public onboarding on only inside that test.

## Security matrix

Own-company access follows the user's existing permission. Cross-company access is denied by the scope, the relation check, or the file guard.

| Resource | Alpha → Alpha | Alpha → Beta | Beta → Alpha |
| --- | --- | --- | --- |
| Products | allow | deny | deny |
| Customers | allow | deny | deny |
| Sales | allow | deny | deny |
| Quotations | allow | deny | deny |
| Payments | allow | deny | deny |
| Bookings | allow | deny | deny |
| WhatsApp contacts | allow | deny | deny |
| WhatsApp conversations | allow | deny | deny |
| Documents | allow when the record's company matches | deny | deny |
| MAI product search | Alpha rows only | no Beta rows | no Alpha rows |

## Compatibility still open

These are explicit exceptions, not a global query:

- Guests and ERP users with no membership still bind the single INTERNAL company by slug, so today's Beyond screens keep their data.
- Console commands do the same until an ACTIVE customer company exists.
- `whatsapp_messages` have no ownership column.
- Property tenancy is still occupancy.
- Public files already on disk were not relocated.
- Ownership columns are still nullable. The audit command is how unowned rows are found.
- Most scheduled commands are still installation-wide. They are safe while BeyondTechWorld is the only company. They must be converted to per-company runs before a real customer company is connected.
- Authenticated click-through of the live ERP was not repeated on production, because this phase was not deployed.

## Production

Not deployed. Production still has the Phase 1C ownership backfill and can still insert a null `cloud_tenant_id` until this code is deployed and migrated.

Do not create Alpha or Beta on the live database as part of deployment. The isolation proof is the test suite.

## Stop

Phase 1E was not started. Public onboarding was not opened.
