# Checkout FieldKit — free version spec

What we are building. Implementers work from this spec, [GLOSSARY.md](../GLOSSARY.md), the ADRs in [docs/adr/](adr/), and the design canvas. The behaviour ledger ([reference-behaviour.md](reference-behaviour.md)) is background only: where it and this spec differ, this spec wins.

Design canvas (eight frames): https://claude.ai/artifact/KVrMLgfy7drw1tCAbDjAZm

**Clean room ([ADR 0001](adr/0001-two-step-clean-room.md)).** Never open the reference plugin's source, wherever it may exist on disk. If this spec does not answer a question, write the question in your pull request; do not go looking.

---

## 1. Platform and constraints

- WordPress 6.6+, WooCommerce 9.0+, PHP 7.4+ ([ADR 0007](adr/0007-wordpress-org-minimums.md)). PHP code must parse on PHP 7.4: no `match`, enums, `readonly`, named arguments, nullsafe `?->`, union or mixed types, constructor property promotion, or `never`. Develop and test on the local PHP 8.5.
- Plugin slug and text domain `checkout-fieldkit`; PHP namespace `CheckoutFieldKit`; prefix `cfk` for options, hooks, handles, CSS classes and meta.
- WordPress coding standards (WPCS) for PHP; `@wordpress/scripts` lint rules for TypeScript and CSS.
- Declares compatibility with High-Performance Order Storage (`custom_order_tables`) and the Block checkout (`cart_checkout_blocks`).
- Order and customer data go through WooCommerce's CRUD objects (`$order->update_meta_data()`, `WC_Customer`), never direct post-meta calls.
- All user-facing strings are translatable and written fresh. Nothing is copied from the reference plugin.
- Must pass the Plugin Check plugin (`wp plugin check checkout-fieldkit`) with no errors.
- The release zip (`npm run zip`) excludes everything listed in `.distignore`.

## 2. Domain model

### 2.1 Field sets and sections

There are two field sets, one per checkout type ([ADR 0002](adr/0002-separate-field-sets-per-checkout-type.md)).

| Checkout type | Sections, in display order |
|---|---|
| Classic | `billing`, `shipping`, `additional` |
| Block | `contact`, `address`, `additional` |

On the Block checkout, the `address` section's fields appear in both the billing and the shipping address forms; there is no billing-only or shipping-only field ([ADR 0008](adr/0008-block-fields-hybrid.md)).

### 2.2 Field

A field is a plain associative array (JSON object). The order of fields within a section's array is their display order.

| Key | Type | Notes |
|---|---|---|
| `name` | string | Permanent identifier. Rule: `^[a-z][a-z0-9_]{0,59}$`. Unique within the field set (across all its sections). |
| `type` | string | One of the field types allowed for the checkout type and section (§2.4). |
| `builtIn` | bool | True for fields WooCommerce defines. Built-in fields cannot be deleted, renamed or change type. |
| `enabled` | bool | Disabled fields are removed from the checkout. |
| `required` | bool | |
| `label` | string | Plain text. For heading and paragraph fields this is the displayed text. |
| `placeholder` | string | |
| `default` | string | Initial value. For multi-value types, a comma-separated list of option values. For checkbox, `"1"` (ticked) or `""`. |
| `options` | array of `{value, label}` | Only for select, radio, multiselect, checkboxgroup. Values are unique and non-empty, except that a select's first option may have an empty value (a "choose one" prompt). |
| `validation` | string[] | Allowed values depend on checkout type (§2.4). |
| `classes` | string[] | CSS classes for the field's wrapper. |
| `headingLevel` | `h2`–`h6` | Heading fields only; default `h3`. |
| `showInEmails` | bool | Custom fields only. Default true. |
| `showOnOrderPages` | bool | Custom fields only. Thank-you page, customer's My Account order view, admin order screen. Default true. |

Unknown keys are dropped on save. Missing keys take their defaults.

### 2.3 Built-in and essential fields

- **Classic built-ins**: what WooCommerce's address fields give for the store's base country with prefix `billing_` and `shipping_` (`WC()->countries->get_address_fields()`), plus `order_comments` in Additional. Built-in fields keep WooCommerce's own type, options and autocomplete; only label, placeholder, default, required, enabled, validation, classes and position are ours to change.
- **Block built-ins**: `email` in Contact; `country`, `first_name`, `last_name`, `company`, `address_1`, `address_2`, `city`, `state`, `postcode`, `phone` in Address. Ours to change: label, required, enabled, position. `email` is locked: always enabled and required, only its position within Contact is ours (and in practice it is alone).
- **Essential** (a disable, or switching required off, needs explicit confirmation in the admin, [ADR 0003](adr/0003-built-in-fields-disable-only.md)):
  - Classic: `billing_email`, `billing_country`, `billing_address_1`, `billing_city`, `billing_state`, `billing_postcode`, `shipping_country`, `shipping_address_1`, `shipping_city`, `shipping_state`, `shipping_postcode`.
  - Block: `country`, `address_1`, `city`, `state`, `postcode`.
  - The confirmation text for each says what stops working (for example, without billing email customers get no order emails).
- Defaults must be computed without our own checkout-field filters applied (avoid recursion and avoid baking our changes into "defaults").

### 2.4 Field types

| Type | Classic | Block | Block sections | Settings that apply |
|---|---|---|---|---|
| `text` | ✓ | ✓ | all | label, placeholder, default, required, validation, classes |
| `number` | ✓ | ✓ | all | as text; numeric validation is implied |
| `email` | ✓ | ✓ | all | as text; email validation is implied |
| `url` | ✓ | ✓ | all | as text; URL validation is implied |
| `tel` (Phone) | ✓ | — | | as text; phone validation is implied |
| `password` | ✓ | — | | as text |
| `hidden` | ✓ | — | | label (admin only), default, classes |
| `textarea` | ✓ | ✓ | contact, additional | label, placeholder, default, required, classes |
| `select` | ✓ | ✓ | all | label, placeholder, options, default, required, classes |
| `multiselect` | ✓ | — | | label, placeholder, options, default, required, classes |
| `radio` | ✓ | ✓ | contact, additional | label, options, default, required, classes |
| `checkbox` | ✓ | ✓ | all | label, default (ticked or not), required, classes |
| `checkboxgroup` | ✓ | — | | label, options, default, required, classes |
| `date`, `time`, `datetime-local`, `month`, `week` | ✓ | — | | label, default, required, classes |
| `heading` | ✓ | ✓ | contact, additional | label (the text), headingLevel, classes |
| `paragraph` | ✓ | ✓ | contact, additional | label (the text), classes |

Classic has 20 types, Block 10. Heading and paragraph collect no value (they are display fields). Validation choices: Classic `email`, `phone`, `postcode`, `state`, `number`, `url`; Block `email`, `phone`, `postcode`, `url`.

### 2.5 Field-level rules (enforced on the server, mirrored in the admin)

1. Name matches the rule in §2.2 and is unique in the field set. New custom fields cannot use a name WooCommerce reserves (any built-in name of either checkout type, `order_comments`, `account_username`, `account_password`, `createaccount`, `ship_to_different_address`, `terms`, `payment_method`, `shipping_method`).
2. Built-in fields: `name`, `type` and `builtIn` cannot change, and the field cannot be removed.
3. Defaults for choice fields must be among the option values; for number, a number.
4. Option values unique and non-empty (except the select prompt case).
5. The type is allowed for the checkout type and section.

The server returns every problem at once, each with section, field index, field key and a message.

## 3. Storage ([ADR 0005](adr/0005-settings-in-versioned-options.md))

| Option | Content |
|---|---|
| `cfk_field_set_classic` | `{"version":1,"sections":{"billing":[...],"shipping":[...],"additional":[...]}}` |
| `cfk_field_set_block` | `{"version":1,"sections":{"contact":[...],"address":[...],"additional":[...]}}` |
| `cfk_settings` | `{"version":1,"localeOverrides":{"label":true,"placeholder":true,"order":true,"required":false,"classes":false}}` |

- When an option does not exist, reading returns the built-in defaults; nothing is written until the first save. Reset deletes the option.
- Field sets are autoloaded (read on every checkout); keep them lean.
- Each stored value carries `version`; a migration step runs on read when the version is older.
- `uninstall.php` deletes these options. It never touches order or customer data.

## 4. Classic checkout behaviour

Late-priority filters on `woocommerce_billing_fields`, `woocommerce_shipping_fields` and `woocommerce_checkout_fields` (for Additional) apply the classic field set. Position becomes WooCommerce priority `(index + 1) × 10`.

- Built-in fields: start from WooCommerce's definition and overlay our label, placeholder, default, required, validation, classes and priority. Disabled fields are removed.
- When every Additional field is disabled, the "Additional information" heading is hidden too (`woocommerce_enable_order_notes_field`).
- Custom field types WooCommerce cannot render (`multiselect`, `checkboxgroup`, date-type fields, `url`, `hidden`, `heading`, `paragraph`, and anything needing changes) are rendered by us through `woocommerce_form_field_{type}` inside WooCommerce's normal wrapper markup, with WooCommerce's required/optional marker, accessible labels and `aria-describedby` for descriptions.
- Validation on submit (`woocommerce_after_checkout_validation`): WooCommerce already checks required, email, phone, postcode and state for its own validators; we add `number` and `url`, and check that choice values are among the options. Only non-empty values are validated. Error messages name the field by its label.
- Shipping-section fields are skipped (not validated, not saved) when the shopper is not shipping to a different address, matching WooCommerce's own rule.
- **Locale overrides** (Settings §7): for each switched-on property among label, placeholder, order (priority) and classes, WooCommerce's per-country value for address fields is discarded so ours applies everywhere. `required` on: our required setting wins in every country; off: the country's rule wins. Country rules that hide a field (for example no State field for countries without states) always apply.
- My Account → Addresses uses the same billing and shipping fields, pre-filled from the customer.

### 4.1 Saving values (classic)

- WooCommerce itself saves any posted field whose key starts with `billing_` or `shipping_`: on the order as meta `_<name>` and on the customer as meta `<name>` (WooCommerce 11.3, `WC_Checkout::create_order` and `process_customer`). We do not save those again; we read them from there.
- Custom fields in Additional (and any custom field whose name lacks those prefixes) are saved by us on the order as meta `<name>` via the order object.
- Before saving, values are sanitised by type: textarea keeps line breaks; email is sanitised as an email; choice fields keep only values that are among the options; multi-value fields are stored as a comma-separated list of option values; checkbox stores `"1"` when ticked and nothing when not. Empty values are not stored; `"0"` is stored.
- New custom fields in Billing or Shipping get a suggested name with that prefix (`billing_`, `shipping_`), and the admin explains that keeping the prefix lets WooCommerce save the value on the customer too.

### 4.2 Showing values

For custom fields only, using the option label rather than the stored value for choice fields, `Yes` for a ticked checkbox, and line breaks kept for textarea:

- **Emails** (`showInEmails`): in WooCommerce's order meta list, in both admin and customer emails, plain-text and HTML.
- **Order pages** (`showOnOrderPages`): a table after the order details on the thank-you page and My Account order view; on the admin order screen under the billing address (billing fields), shipping address (shipping fields) or general details (additional fields).
- Only fields that still exist in the field set are shown; values left behind by renamed or deleted fields are not shown (the admin warns about this when renaming).

## 5. Block checkout behaviour ([ADR 0008](adr/0008-block-fields-hybrid.md))

- **Address section → WooCommerce's official API.** Custom fields are registered with `woocommerce_register_additional_checkout_field()` at location `address`, id `checkout-fieldkit/<name>`. Allowed types there: text, number, email, url (as text with input attributes and validation), select, checkbox. WooCommerce stores, validates required-ness, and displays these values itself. Built-in address fields get our label, required, enabled (hidden) and order by adjusting WooCommerce's default address field data and country locale data, only when the store uses the Block checkout.
- **Contact and Additional sections → our own checkout inner blocks.** Two inner blocks: one placed after the Contact information block, one in the order/additional information area. They must be inserted into existing Checkout pages automatically (no manual editing needed), render all custom fields of their section in our order with WooCommerce block checkout styling, keep values in checkout state, and send them with the order through a Store API extension (namespace `checkout-fieldkit`). Server-side validation mirrors §2.5 and §4 (required, email, url, number, phone, postcode, choice values) and blocks the order with a field-specific error.
- Values from our inner blocks are saved on the order as meta `<name>` (sanitised as in §4.1). Display follows §4.2 (emails, thank-you/My Account, admin order screen under general details).
- Display flags for API-backed address fields map onto WooCommerce's own options as far as it allows (for example `show_in_order_confirmation`); any flag WooCommerce cannot honour is listed in the pull request and shown as a note in the editor.
- Requires WooCommerce 8.9+ for any block features; on older versions block features stay off and the admin says why.

## 6. Admin app

A React app ([ADR 0004](adr/0004-admin-on-wordpress-react-toolchain.md)) at **WooCommerce → Checkout Fields** (page slug `checkout-fieldkit`), capability `manage_woocommerce`. Visual design follows the canvas: system font stack (`-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif`), accent `#3858e9`, neutral greys, 10 px card radius, CSS variables for all colours. No web fonts.

### 6.1 Shell (frames 1, 2)

- App header: logo mark and name, tabs **Fields · Settings · Tools**, unsaved-changes indicator (count), **Discard**, **Save changes** (disabled when nothing changed, "All changes saved" shown instead of the count).
- Location is kept in the URL query (`tab`, `checkout`, `section`) so reloads and links land in the same place.
- Leaving the page with unsaved changes asks for confirmation.
- Uses `@wordpress/api-fetch` with the REST nonce. Errors from a save are shown next to the affected fields and in a summary; nothing is lost on error.

### 6.2 Fields screen (frames 1, 2, 2b, 3b)

- Title, intro line, and a **Classic checkout / Block checkout** switch. Each side shows a pill: **In use** for the active checkout type, **Not in use** for the other. The screen opens on the active type.
- When viewing the type not in use: a notice explains shoppers don't see these fields right now.
- Section tabs with field counts. Block Address shows a note that its fields appear in both address forms.
- Field rows: drag handle; select checkbox; label with **Custom** / **Essential** tags and the field name below in monospace; type; validation; **Required** switch; **Enabled** switch; **Edit**; a **More** menu (Copy to the other checkout, Delete for custom fields).
- Disabled rows are greyed but remain editable.
- Reorder by drag (dnd-kit) or keyboard: focus the handle, Space to pick up, arrow keys to move, Space to drop, Escape to cancel, with screen-reader announcements.
- Selecting rows shows a selection bar: count, Turn on, Turn off, Copy to the other checkout, Delete (disabled with a reason when any selected field is built-in), Clear selection.
- Turning off an essential field, or switching its Required off, opens a confirmation dialog (frame 3b) naming the consequence; "Keep it on" is the default focus.
- Copy to the other checkout: copies a custom field into the same-named section if it exists, otherwise the closest (`billing`/`shipping` → `address`, `contact` → `billing`, `additional` → `additional`); refused with a reason when the type is not allowed there or the name is taken.
- Empty section: illustration, short explanation, examples, **Add field**.
- Add field opens the editor drawer with a new custom field.

### 6.3 Field editor drawer (frame 3)

- Right-side drawer (modal, focus trapped, Escape closes), title "Add field" or "Edit field", subtitle with checkout type, section and Custom/Built-in.
- **Live preview** of the field as a shopper sees it, updating as you type.
- Error summary at the top listing problems as links to the inputs; inline errors under inputs (`aria-invalid`, `aria-describedby`).
- Groups: **Basics** (type, label, field name, placeholder), **Options** (for choice types: rows of saved value + shown text, drag/keyboard reorder, remove, Add option; then "Selected by default"), **Rules** (Required; Validation where applicable), **Where the answer shows** (Order emails; Order pages — custom fields only), **Advanced** (CSS classes; heading level for headings).
- Only settings that apply to the chosen type are shown (§2.4). Types not allowed in the current section are shown disabled with the reason.
- Field name: auto-suggested from the label for new fields (with section prefix for classic billing/shipping); locked for built-in fields; renaming a custom field shows the warning that existing orders' answers will no longer be shown.
- Footer: **Delete field** (custom only), **Cancel**, **Apply**, and a line saying Apply adds to unsaved changes and nothing reaches the checkout until Save changes.

### 6.4 Settings screen (frame 4)

Card "Address fields in different countries": short explanation with examples, five switches with descriptions (Keep my labels, placeholders, field order, required settings, CSS classes), a "Changed" tag on switches that differ from the saved value, Restore defaults. Saved with the header's Save changes.

### 6.5 Tools screen (frame 5)

- **Export**: tick Classic fields, Block fields, Settings (with counts); Download file → `checkout-fieldkit-<site-host>-<YYYY-MM-DD>.json`. The file contains no order or customer data.
- **Import**: choose a file; the server validates it and returns a preview per target (Replace with added/removed/changed field names, or Keep when absent from the file); optional "Download a copy of my current setup first" (default on); **Import and replace**; Cancel. Invalid files get a clear message and change nothing.
- **Reset**: Classic checkout fields, Block checkout fields, Settings — each with its consequence (for example how many custom fields are deleted) and a confirmation dialog. Orders keep their saved answers.

### 6.6 Accessibility (free-version quality bar, [ADR 0006](adr/0006-free-first-pro-parked.md))

Everything works by keyboard with visible focus; controls are real buttons, links and inputs with labels; switches expose their state; dialogs and the drawer trap focus and return it; live regions announce reorder moves and save results; text meets 4.5:1 contrast.

## 7. REST API

Namespace `checkout-fieldkit/v1`. Every route requires `manage_woocommerce`. Bodies and responses are JSON.

| Method and route | Purpose |
|---|---|
| `GET /context` | `{ activeCheckoutType: "classic" \| "block" \| "unknown", checkoutPageUrl, blockSupported: bool, sections, fieldTypes, essentialFields, validations }` — everything the admin needs that isn't stored data. |
| `GET /field-sets/{classic\|block}` | The stored or default field set. |
| `PUT /field-sets/{classic\|block}` | Replace the whole field set. `422` with `{code:"cfk_invalid_field_set", data:{status:422, errors:[{section, index, key, message}]}}` when invalid; nothing is saved. |
| `GET /settings`, `PUT /settings` | Read or replace settings. |
| `GET /export?include=classic,block,settings` | `{plugin:"checkout-fieldkit", format:1, exportedAt, classic?, block?, settings?}` |
| `POST /import/preview` | Body: an export document. Returns `{targets:[{target, action:"replace"\|"keep", added:[names], removed:[names], changed:[names]}]}` or `422`. |
| `POST /import` | Body: an export document. Validates everything first, then replaces the included targets. |
| `POST /reset` | Body `{target:"classic"\|"block"\|"settings"}`. |

Active checkout type: `block` when the page set as WooCommerce's Checkout page contains the `woocommerce/checkout` block, `classic` when it contains the `[woocommerce_checkout]` shortcode or the classic shortcode block, else `unknown`.

## 8. Extension points (for a future Pro add-on)

Filters: `cfk_field_types` (types per checkout type), `cfk_sections`, `cfk_field_defaults`, `cfk_validate_field` (extra field rules), `cfk_render_field_classic`, `cfk_sanitize_value`, `cfk_display_value`, `cfk_admin_settings` (data passed to the admin app). Actions: `cfk_field_set_saved`, `cfk_order_values_saved`. Each documented with a docblock.

## 9. Testing (test-driven)

- **PHP**: PHPUnit with the WordPress test suite and WooCommerce loaded. Domain, storage, REST, classic rendering/validation/saving/display and block registration/saving are covered by tests written before the code. Each developer uses their own test database.
- **JS**: Jest (`wp-scripts test-unit-js`) with Testing Library for the draft store, validation helpers and key components.
- **Manual**: the final task places real orders on both checkout types on the local site and checks every display location.
