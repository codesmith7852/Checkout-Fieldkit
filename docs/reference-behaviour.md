# Reference plugin behaviour ledger

How the reference plugin ("Checkout Field Editor (Checkout Manager) for WooCommerce", free version 2.2.1) behaves, in plain language. Implementers read this instead of the reference plugin's source ([ADR 0001](adr/0001-two-step-clean-room.md)). Nothing here is code, CSS, or user-facing wording to copy; where the reference's wording is mentioned, it is described, not quoted for reuse.

**Evidence key.** *Src* = read in the reference source (file named); *Run* = observed on the test site; *Docs* = its wordpress.org listing or screenshots. *Confidence*: high (read directly and unambiguous), medium (read, but depends on runtime conditions), low (inferred).

**What this ledger is not.** It is not our spec. Where we deliberately differ (for example [ADR 0003](adr/0003-built-in-fields-disable-only.md)), the ADR wins. Questions this ledger does not answer go back to the analysis side, not to the reference source.

Analysed against WooCommerce 11.3.0 and WordPress 7.1.4 on 2026-11-13.

---

## 1. Admin structure

| # | Behaviour | Evidence | Confidence |
|---|---|---|---|
| 1.1 | One admin page, added under the WooCommerce menu, labelled "Checkout Form". | Src `admin/class-thwcfd-admin.php`; Docs screenshot 1 | high |
| 1.2 | Access requires the "manage WooCommerce" capability; a filter can switch it to "manage options", and any other value is ignored. | Src `includes/utils/class-thwcfd-utils.php` (capability helper) | high |
| 1.3 | Top-level tabs: Checkout Fields, Advanced Settings, Premium Features (upsell), Other Free Plugins (cross-promotion). | Src `admin/class-thwcfd-admin-settings.php` | high |
| 1.4 | Inside Checkout Fields, a two-way switch picks Classic or Block checkout. It defaults to Classic regardless of what the store uses; an info tooltip warns that fields only appear if the store uses that checkout type. | Src `admin/class-thwcfd-admin-settings-general.php` (checkout type switch) | high |
| 1.5 | Classic has three sections as text links: Billing, Shipping, Additional. Block shows three sections in its navigation: Contact Information, Address, Additional Information. (A fourth, "order", exists in its data but is not shown in the free navigation.) | Src `admin/class-thwcfd-admin-settings-general.php`, `admin/class-thwcfd-admin-settings-block-fields.php` (section output skips everything except address, contact and additional_info) | high |
| 1.6 | Every state change (switching section, saving a field, saving the list) is a full page reload with a notice at the top. | Src (form posts back to the same page) | high |

## 2. Field list (both checkout types)

| # | Behaviour | Evidence | Confidence |
|---|---|---|---|
| 2.1 | Each section shows a table: drag handle, select checkbox, name, type, label, placeholder, validations, required (tick or dash), enabled (tick or dash), edit action. | Src general settings (table heading row); Docs screenshot 1 | high |
| 2.2 | Toolbar above and below the table: Add field, Remove, Enable, Disable (acting on the ticked rows), Reset to default fields, Save changes. | Src (actions row) | high |
| 2.3 | Remove, Enable, Disable and drag reordering only mark rows in the page; nothing is stored until Save changes is pressed. Adding or editing a field in the popup saves that field immediately. So the page has two different save models. | Src (save fields vs save-or-update field handlers) | high |
| 2.4 | On save, each field's position becomes a priority of (position + 1) × 10. A new field gets the current highest priority + 10, so it lands at the end. | Src utils (priority helper) | high |
| 2.5 | Disabled rows are greyed out and their Edit button is disabled, so a disabled field must be re-enabled before it can be edited (classic). | Src general settings row rendering | high |
| 2.6 | Remove deletes any field, including WooCommerce's built-in ones, with no warning. | Src save-fields handler (deleted flag unsets the field) | high |
| 2.7 | Block list only: an action to duplicate a field, which opens the editor pre-filled as a new field. | Src block fields settings (copy button per row) | high |
| 2.8 | Block list only: the contact email field cannot be edited, and the country field cannot be deleted. | Src block fields settings (not-editable / not-deletable row markers) | high |
| 2.9 | Empty section message: tells the user to click Add Field. | Src block fields settings | high |

## 3. Field editor (popup)

| # | Behaviour | Evidence | Confidence |
|---|---|---|---|
| 3.1 | A modal popup with a left rail (only one tab, "Basic Info", in free) and a "Save & Close" button. | Src `admin/class-thwcfd-admin-form-field.php`; Docs screenshots 3, 4 | high |
| 3.2 | Classic field types (20): text, number, hidden, password, email, phone, textarea, radio, select, multiselect, checkbox, checkbox group, heading, datetime-local, date, time, month, week, URL, paragraph. | Src admin form field (type list); Docs | high |
| 3.3 | Classic settings available per type: | Src admin form field (per-type form fragments) | high |
| | • text, password, phone, email, URL, select, radio: label, placeholder, default value, CSS classes, validations, required, enabled, show in emails, show on order pages. | | |
| | • number, multiselect: same as above but no validations choice (number always gets numeric validation). | | |
| | • textarea, date-type fields (datetime-local, date, time, month, week): label, default, CSS classes, validations, required, enabled, show in emails, show on order pages (no placeholder for date types). | | |
| | • checkbox: label, CSS classes, value when ticked (stored as "default"), checked by default, required, enabled, show in emails, show on order pages. | | |
| | • checkbox group: label, default, CSS classes, required, enabled, show in emails, show on order pages. | | |
| | • hidden: label, default value, CSS classes, enabled, show in emails, show on order pages. | | |
| | • heading: text (stored as label), heading level H1–H6 (default H3), CSS classes, enabled. | | |
| | • paragraph: text (stored as label), CSS classes, enabled. | | |
| 3.4 | Select, multiselect, radio and checkbox group have an options table: rows of value + display text, add/remove buttons, drag to reorder. | Src admin form field (options fragment) | high |
| 3.5 | Option values: if a value is left blank it is derived from the display text (lowercased, spaces and symbols removed). The first option of a select may keep a blank value, which acts as an empty choice. | Src utils (options array helper) | high |
| 3.6 | Validation choices for classic: email, phone, postcode, state, number, URL (multi-select). | Src admin form field | high |
| 3.7 | Field name rule: must start with a lowercase letter or underscore, then only lowercase letters, digits and underscores. Type and name are required. | Src `admin/assets/js/thwcfd-admin.js` (name check and form validation) | high |
| 3.8 | Default value checks: for select and radio it must match one of the option values; for multiselect and checkbox group each comma-separated default must match; for number it must be a whole number (optionally negative). | Src admin JS (form validation) | high |
| 3.9 | No check that a new custom field's name is unique within the section or across sections was found; saving a field with an existing name in the same section overwrites it. The block editor shows a hint asking users to keep names unique across sections. | Src (save handler stores by name; no duplicate check found in admin JS) | medium |
| 3.10 | Renaming a custom field stores it under the new name and drops the old one. Orders placed before keep their value under the old name, which is then no longer displayed anywhere. | Src save-or-update handler | high |
| 3.11 | For built-in fields, the editor locks name, type, "show in emails" and "show on order pages"; label, placeholder, default, CSS classes, validations, required and enabled stay editable. | Src admin JS (custom vs built-in toggling) | high |
| 3.12 | Labels are allowed limited HTML (post-level allowed tags); other text settings are plain text. CSS classes are entered comma-separated and stored as a list. | Src save handler (field from posted data) | high |
| 3.13 | Block field types (10): text, checkbox, select, radio, number, URL, email, paragraph, heading, textarea. | Src `admin/class-thwcfd-admin-form-block-field.php` | high |
| 3.14 | Block settings per field: name, type, label, placeholder (some types), default value, validation (single choice: email, phone, URL), wrapper CSS classes, required, enabled, "save to order" (default on), "save to customer account" (default off), and five display switches: admin emails, customer emails, admin order screen, customer order details (default on for these four), My Account page (default off). | Src block field form | high |

## 4. Classic checkout: what shoppers get

| # | Behaviour | Evidence | Confidence |
|---|---|---|---|
| 4.1 | The plugin hooks WooCommerce's billing, shipping and checkout field filters at a late priority (1000, filterable) and replaces each section's fields with its stored list. If nothing is stored for a section, WooCommerce's own fields are used untouched. | Src `public/class-thwcfd-public-checkout.php` | high |
| 4.2 | For a built-in field, it starts from WooCommerce's own definition and overlays only label, placeholder, default, CSS classes, label classes, validations, priority, and (depending on 6.x) required. WooCommerce's type, options and autocomplete are kept. | Src public checkout (prepare checkout fields) | high |
| 4.3 | Disabled fields are removed from the checkout entirely. | Src public checkout | high |
| 4.4 | If every field in Additional is disabled, WooCommerce's "Additional information" heading is hidden too. | Src public checkout (order notes switch) | high |
| 4.5 | Field types WooCommerce cannot render itself (checkbox group, multiselect, date-type fields, URL, hidden, heading, paragraph, and a reworked checkbox) are drawn by the plugin as standard HTML inputs inside WooCommerce's usual field wrapper, with WooCommerce's required/optional marker. | Src public checkout (form field renderers) | high |
| 4.6 | Select and multiselect use WooCommerce's enhanced searchable dropdown by default (filter can turn it off). | Src public checkout | high |
| 4.7 | Heading renders the label as an H1–H6 element; paragraph renders the label as a paragraph; hidden renders a hidden input carrying the default value. | Src public checkout | high |
| 4.8 | Number and URL validations are checked on order submit by the plugin; email, phone, postcode and state validations are WooCommerce's own. Validation only runs on non-empty values; emptiness is handled by WooCommerce's required check. | Src public checkout (validation), WooCommerce core | high |
| 4.9 | The Shipping section is skipped (not validated, not saved) when the shopper is not shipping to a different address, unless the store is set to "ship to billing address only". | Src public checkout (skip-fieldset rule) | medium |
| 4.10 | On My Account → Addresses, the same billing and shipping field lists are used, pre-filled from the customer's saved values. A filter can hide custom fields there. | Src public checkout (billing/shipping fields on edit-address) | medium |
| 4.11 | A filter (off by default) keeps a hidden country value on the checkout when the country field is disabled, so payment gateways that read it (for example Stripe) keep working. | Src public checkout (hidden country compat) | high |

## 5. Classic checkout: saving and showing values

| # | Behaviour | Evidence | Confidence |
|---|---|---|---|
| 5.1 | Only enabled custom fields are saved by the plugin, as order metadata under the field name exactly (no leading underscore, so they also show in the order's custom fields box). Saved through WooCommerce's order API, so it works with High-Performance Order Storage. | Src public checkout (update order meta) | high |
| 5.2 | Empty values are not saved. A value of zero is also not saved unless a filter allows it. | Src public checkout | high |
| 5.3 | Select and radio values are saved only if they match an option value; multiselect and checkbox group keep only matching values and store them comma-separated. | Src public checkout | high |
| 5.4 | Checkbox: when ticked, the stored value is the field's configured "value when ticked" if set, otherwise the submitted value; when unticked nothing is stored (a filter can supply an "off" value). | Src public checkout | high |
| 5.5 | Shipping phone is left to WooCommerce, which handles it natively since WooCommerce 5.6. | Src utils (special fields list) | high |
| 5.6 | Values are shown with the option's display text rather than its stored value, for choice fields. Textarea line breaks are kept. | Src utils (option text), public checkout | high |
| 5.7 | Emails: custom fields with "show in emails" on are added to WooCommerce's order meta list in both admin and customer emails (one switch for both). | Src public checkout (email order meta fields) | high |
| 5.8 | Thank-you page and My Account order view: a small table after the order details lists custom fields with "show on order pages" on. Shipping fields are skipped if the order has no shipping address. | Src public checkout (order details after order table) | high |
| 5.9 | Admin order screen: billing custom fields appear under the billing address, shipping ones under the shipping address, additional ones under the general order details, each as bold label and value. | Src general settings (admin hooks) | high |
| 5.10 | Labels, placeholders and option texts are passed through WPML string translation when WPML is active, then through the plugin's and WooCommerce's own translation files. Labels and options are registered with WPML when a field is saved. | Src utils (translate dynamic text), general settings (WPML support) | high |

## 6. Advanced settings: locale overrides

WooCommerce changes address fields per country (for example renaming "State" to "County" or making postcode optional). These switches decide whether the plugin's settings win.

| # | Behaviour | Evidence | Confidence |
|---|---|---|---|
| 6.1 | Five switches: label override, placeholder override, CSS class override, priority (order) override, required override. | Src `admin/class-thwcfd-admin-settings-advanced.php`; Docs screenshot 7 | high |
| 6.2 | When label, placeholder, class or priority override is on, WooCommerce's per-country values for that property are discarded, so the plugin's values apply in every country. | Src public checkout (country locale filters) | high |
| 6.3 | Required override: when on, the plugin's required setting wins for every country. When off, the country's required setting wins. Countries' "hidden" rules (for example no state field for countries without states) always apply. | Src public checkout (prepare address fields) | high |
| 6.4 | Defaults when nothing has been saved yet: label, placeholder and priority overrides behave as on; class and required overrides behave as off. However, the first time any field is saved, the plugin stores its settings with all five switched on. | Src public checkout (override helpers), utils (setup advanced settings) | medium (verify on the test site) |
| 6.5 | Extra handling makes WooCommerce's "hide shipping costs until an address is entered" option work with the required override on WooCommerce 9.8+. | Src public checkout (modify address fields) | medium |
| 6.6 | Reset to default on this tab restores the five switches. | Src advanced settings | high |

## 7. Backup and import

| # | Behaviour | Evidence | Confidence |
|---|---|---|---|
| 7.1 | The Advanced Settings tab shows a large text box holding all settings as one encoded text blob. Users copy it to back up, or paste one in and press Import. | Src advanced settings (import/export); Docs screenshot 7 | high |
| 7.2 | The blob covers the three classic sections and the advanced settings only. Block checkout fields are not included. | Src advanced settings (prepare plugin settings) | high |

## 8. Reset

| # | Behaviour | Evidence | Confidence |
|---|---|---|---|
| 8.1 | Classic "Reset to default fields" asks for confirmation, then deletes all three classic sections at once (not just the current one), returning to WooCommerce's own fields. | Src general settings (reset) | high |
| 8.2 | The built-in defaults it falls back to are WooCommerce's address fields for the store's base country, plus Order Notes in Additional. | Src utils (get fields) | high |
| 8.3 | Block "Reset to default fields" restores all block sections to their defaults. | Src block fields settings | high |

## 9. Block checkout: what shoppers get

| # | Behaviour | Evidence | Confidence |
|---|---|---|---|
| 9.1 | Needs WooCommerce 8.8 or later; on older versions the block features switch off. | Src `block/class-thwcfd-block.php` | high |
| 9.2 | Custom fields in the Address section are registered through WooCommerce's official additional-checkout-fields API at the "address" location, so WooCommerce renders them in both the billing and shipping address forms. | Src block (register additional address fields) | high |
| 9.3 | Built-in address fields: the plugin changes their label, required state and order, and hides disabled ones, by adjusting WooCommerce's default address field data and per-country rules. The contact email is never changed. | Src block (update default fields data) | high |
| 9.4 | Custom fields in Contact and Additional Information are not registered through the official API. The plugin ships its own checkout inner blocks that draw these fields and send their values with the order through a Store API extension. | Src `block/class-thwcfd-block-integration.php`, `block/class-thwcfd-block-extend-store-endpoint.php`, `block/assets/dist/*` | high |
| 9.5 | Saved values: each field is stored on the order under its name, and additionally the whole section is stored as one list under the section's name. If "save to customer account" is on, the same is written to the customer's profile. | Src block (update order from request) | high |
| 9.6 | Validation on block checkout: email, phone and postcode rules for address custom fields are checked on submit. | Src block (validate additional field) | high |
| 9.7 | Required checkboxes are not supported before WooCommerce 9.8; on older versions they are silently made optional. | Src block | high |
| 9.8 | Display: separate switches decide whether values show in admin emails, customer emails, the admin order screen, the customer's order details and My Account. Section titles can be shown above each group. | Src `block/class-thwcfd-block-order-data.php` | high |

## 10. What the official WooCommerce API supports today

Not reference behaviour, but it decides how block fields can be built. From WooCommerce 11.3's own source (`src/Blocks/Domain/Services/CheckoutFields.php`):

- Supported field types: **text, select, checkbox, date**. No textarea, radio, number, email, URL, heading or paragraph type.
- Locations: **contact, address, order**. "additional" is a deprecated alias for "order".
- Fields can carry HTML attributes and a validation schema, can be hidden or required conditionally, and can opt out of the order confirmation page.
- WooCommerce stores the values itself and shows them in the admin order screen, emails and the customer's account without extra code.

So 4 of the reference's 10 block types (text, select, checkbox, plus email/URL/number as validated text) map onto the official API. Textarea, radio, heading and paragraph need a custom inner block, which is what the reference does for whole sections (9.4).

## Unknowns to verify on the test site

1. 6.4: which locale-override switches are actually on after the first field save.
2. 3.9: whether any duplicate-name check exists that the source read missed.
3. 4.10: whether custom billing/shipping field values are saved to the customer's profile on classic checkout (WooCommerce core may do this for any field in those sections).
4. 9.4: exactly where the Contact and Additional Information inner blocks appear on the block checkout, and whether the merchant must insert them in the editor.
5. 7.1: the blob's encoding (only relevant if we ever import it, which [ADR 0006](adr/0006-free-first-pro-parked.md) rules out).
