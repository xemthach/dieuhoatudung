# Quote Form Contract

## Purpose

`/bao-gia` creates an actionable request for Sales; it does not promise an instant formal quotation. The canonical journey is three steps: need, optional useful context, contact.

The minimum valid submission is:

- customer name;
- Vietnamese phone number;
- a server-issued submission token and detected entry context.

All technical, sizing, budget and location details improve follow-up but must not block a customer who does not know them.

## Entry contexts

| Context | Source | Trusted context |
|---|---|---|
| `direct` | `/bao-gia` | None |
| `product` | Product slug/id | Product is reloaded from the database; client product labels are not authoritative |
| `calculator` | Calculator CTA | Non-PII calculator context is read from the server session |
| `category` | Brand/category slug | Existing Brand/ProductCategory is resolved by slug |
| `campaign` | UTM or supported click identifier | Existing tracking fields only |

No name, phone, email, address or free text is placed in the URL. Calculator context contains only method, rule version, sizing inputs and result.

## Field governance

P0 fields are `full_name`, `phone`, `submission_token`, and `entry_context`. P1 fields materially improve response: Product/Calculator context, project type, approximate area, timeline, installation scope and region. P2/P3 technical fields remain accepted for backward compatibility but are not promoted into the initial customer funnel.

The complete field-by-field contract is maintained in [quote_form_field_inventory.csv](../reports/final/artifacts/quote_form_field_inventory.csv). Future changes must document:

1. the Sales decision enabled by the field;
2. when it is required;
3. whether it can be derived or delayed;
4. PII handling;
5. validation and destination.

Do not make a field mandatory merely because a database column exists.

## Validation and unknown values

- Phone accepts common spaces, dots, hyphens, parentheses, `+84`, or `0084`; it is normalized to a local `0...` representation before persistence.
- Email is optional.
- Area, height, project type, budget, timeline and province are optional.
- Explicit `chua_ro` states exist where a categorical unknown is useful. Empty numeric values mean “not supplied”, never zero.
- Direct quote BTU is calculated only when both area and a supported project type exist. Missing context is not silently mapped to an office.
- Calculator-origin BTU is preserved from the server-side calculator context instead of being recomputed from client input.

`provided_fields` records validated fields actually present in the request. This distinguishes customer-provided values from legacy database defaults without rewriting historical rows.

## Persistence and idempotency

`QuoteSubmissionService` writes `QuoteRequest` and its linked `Lead` in one database transaction. `quote_requests.submission_token` is unique. A repeated browser/network submission returns the existing request and does not create a second Lead, conversion event, or email notification.

Mail and optional offline conversion processing occur after the Quote/Lead transaction. Mail failure is logged by quote ID and does not roll back or lose the request. Logs must not contain the complete contact payload.

Repeated requests from the same phone with a new submission token are allowed because they can represent a legitimate new project. Contact merging is a future CRM policy, not a frontend guess.

## Privacy and analytics

Generic analytics may receive event name, step number, entry context, Product ID and non-PII campaign context. It must not receive name, phone, email, address or message. No partial form is persisted server-side. This implementation does not autosave PII.

Existing retention and role permissions govern `QuoteRequest`, `Lead`, mail log and conversion records. Retention duration is not currently encoded in this form workflow and requires an operator policy.

## Admin contract

Sales sees a source badge, customer contact, Product snapshot, optional Calculator method/rule/result, need, budget and status. The existing `QuoteRequestStatus` enum remains authoritative. The form does not introduce Sales assignment or a second status system.

## Deployment

Run the new migration before serving the updated controllers:

```bash
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

The expected migration count after this change is 93. The migration changes only quote workflow schema; it does not update Product or catalog technical data.
