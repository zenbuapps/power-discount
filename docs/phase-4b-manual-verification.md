# Phase 4b Manual Verification

Activate `power-discount`. Confirm `WooCommerce → Power Discount` appears in the admin menu (requires `manage_woocommerce` capability).

## List page

- [ ] Visit `/wp-admin/admin.php?page=power-discount`. Empty table renders if no rules exist.
- [ ] Click "Add New" → goes to edit page with empty form scaffold.

## Create rule

- [ ] On the new rule form, enter title `Test 10%`, leave defaults, set `config_json` to `{"method":"percentage","value":10}`. Click Create.
- [ ] After save, redirected to edit page with success notice. List page now shows the rule.

## Edit existing

- [ ] Click rule title → edit form pre-fills.
- [ ] Modify priority to 5, save → success notice, list shows priority 5.

## Validation

- [ ] Open edit form, change `config_json` to `{not json`. Save → error notice "Invalid JSON in config field." Form returns with original input lost (acceptable Phase 4b behaviour).
- [ ] Clear title field. Save → error "Rule title is required."

## AJAX status toggle

- [ ] Click "Toggle" link in Status column → page reloads, status flips.

## Duplicate

- [ ] Click "Duplicate" → redirected to edit page of new copy with `(copy)` suffix, status disabled.

## Delete

- [ ] Click "Delete" → confirm dialog, then page reloads, rule gone.

## Functional verification with a real cart

Create a `simple` rule with `config_json = {"method":"percentage","value":10}`, no filters, no conditions, status enabled.

- [ ] Add product to cart → 10% off applies.

## Known Gaps → Phase 4c/4d

- No frontend price table / shipping bar / saved label
- No reports page
- No React GUI for strategy/condition/filter (currently raw JSON textarea — power-user only)
- Pagination on the list page (low priority for typical rule counts)
