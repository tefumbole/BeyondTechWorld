# Beyond Cloud settings classification

`general_settings` stays one installation-wide row. It was not converted into tenant settings in Phase 1C.

Authoritative company facts used for the INTERNAL CloudTenant came from the default biller (id 1) and from `general_settings.site_title`. Conflicts are recorded in `BEYOND_CLOUD_PHASE_1C_REPORT.md`.

## general_settings

| Column | Class | Later home |
| --- | --- | --- |
| site_title | Company specific | CloudTenant `system_name` |
| site_logo | Company specific | CloudTenant logo, per company |
| currency | Company specific | CloudTenant `currency` |
| currency_position | Company specific | Company settings |
| invoice_format | Company specific | Company settings |
| default_biller_id | Company specific | The company's default biller |
| default_warehouse_id | Company specific | Company settings |
| date_format | Company specific | Company settings |
| theme | Company specific | Company settings |
| staff_access | Company specific | Company settings |
| state | Company specific | Company settings |
| letter_serial_no | Company specific | Company settings |
| developed_by | Platform global | Platform settings |
| app_version | Platform global | Platform settings |
| email_header | Shared, needs a decision | Split platform mail chrome from company mail chrome |
| email_footer | Shared, needs a decision | Same split |
| email_water_mark | Shared, needs a decision | Same split |
| commission | Company specific | Company settings |

`general_settings.currency` currently points at `currencies.id` 3, code `003`, name `RWF`. Cloud plans are priced in XAF. The INTERNAL CloudTenant currency is XAF. The settings row was not changed.

## site_settings

`site_settings` is a key/value table (38 rows) for the public Beyond site. Treat the rows as company specific to BeyondTechWorld until each key is reviewed. Do not copy the table into every future company as-is.

## Other single-row settings

| Table | Class |
| --- | --- |
| pos_setting | Company specific |
| hrm_settings | Company specific |
| reward_point_settings | Company specific |
| whatsapp_settings | Company specific, after a WhatsApp connection exists |
| wa_announcement_settings | Company specific |
| contract_settings | Company specific |
| be_share_settings | Shared, needs a decision (shareholder portal, not CloudTenant) |

## Billers

Billers are invoice headers. The single biller, Beyond Enterprise, is not a CloudTenant.

Later relationship:

```
CloudTenant
  → one or more billers
```

The concepts stay separate.
