# Beyond Cloud Phase 1C data integrity

Rehearsal database: `beyond_cloud_rehearsal`, loaded from the 2026-10-02 backup of `beyondtechworld_laravel`. The live database was not written.

The ownership command changes only `cloud_tenant_id`. It does not delete, insert, merge, or recreate business rows. The test company insert is a separate optional seed and is listed on its own.

## Before and after the ownership write

| Table | Before | After | Assigned to BeyondTechWorld | Ambiguous | Errors |
| --- | --- | --- | --- | --- | --- |
| products | 471 | 471 | 471 | 0 | none |
| categories | 32 | 32 | 32 | 0 | none |
| brands | 35 | 35 | 35 | 0 | none |
| units | 18 | 18 | 18 | 0 | none |
| warehouses | 2 | 2 | 2 | 0 | none |
| customers | 744 | 744 | 744 | 0 | none |
| suppliers | 1 | 1 | 1 | 0 | none |
| sales | 30 | 30 | 30 | 0 | none |
| quotations | 25 | 25 | 25 | 0 | none |
| payments | 30 | 30 | 30 | 0 | none |
| bookings | 64 | 64 | 64 | 0 | none |
| whatsapp_contacts | 1494 | 1494 | 1494 | 0 | none |
| whatsapp_conversations | 172 | 172 | 172 | 0 | none |
| product_sales | 66 | 66 | 0 (derived) | 0 | skipped |
| product_quotation | 63 | 63 | 0 (derived) | 0 | skipped |
| booking_products | 61 | 61 | 0 (derived) | 0 | skipped |
| whatsapp_messages | 2312 | 2312 | 0 (derived) | 0 | skipped |

Dry-run before the write reported `would_assign` equal to the unowned count and `ambiguous=0` on every direct table.

## Relationships after the write

| Child | Parent | Orphans |
| --- | --- | --- |
| sales | customers | 0 |
| quotations | customers | 0 |
| bookings | customers | 0 |
| product_sales | sales | 0 |
| product_quotation | quotations | 0 |
| booking_products | bookings | 0 |
| whatsapp_messages | whatsapp_conversations | 0 |

## Test company

After the Beyond write, a test customer company was inserted with two new rows. Existing rows were not given to it.

| Record | cloud_tenant_id |
| --- | --- |
| Phase 1C Test Customer (CUSTOMER) | its own id |
| Test Tenant Product | test company |
| Test Tenant Customer | test company |

A second execute then reported products `total=472`, `already_owned=471`, `ambiguous=1`, `would_assign=0`. The test product stayed on the test company. Customers: `already_owned=744`, `ambiguous=1`.

`whatsapp_contacts.normalized_phone` stayed unique on the single column `whatsapp_contacts_normalized_phone_unique`.

## Rollback

`migrate:rollback --step=5` removed the nullable columns and the entitlement table. Product count stayed 472 (the test row was still there). A product name was still readable, so business rows were not dropped with the column.

The backup was then loaded again:

| Check | After restore |
| --- | --- |
| products | 471 |
| sales | 30 |
| customers | 744 |
| products.cloud_tenant_id | absent |
| cloud_tenants | 0 |

The rehearsal database was dropped after that check. Live `beyondtechworld_laravel` stayed at 471 products, 0 cloud tenants, and no `products.cloud_tenant_id`.
