# Block checkout fields: WooCommerce's official API for Address, our own inner blocks for Contact and Additional

The Block checkout offers 10 field types for parity with the reference plugin. WooCommerce's official additional-checkout-fields API (as of WooCommerce 11.3) supports only text, select, checkbox and date, in three locations: contact, address and order. So:

- **Address section → official API.** Custom address fields are registered with WooCommerce's API, which renders them in both address forms and stores, validates and displays them itself. Only types the API can express are allowed here: text, select and checkbox, plus email, URL and number as text fields with input attributes and validation.
- **Contact and Additional sections → our own checkout inner blocks.** All custom fields in these two sections, of any of the 10 types, are rendered by our blocks and their values travel with the order through a Store API extension, then are saved and displayed by our code. This is the only way to offer textarea, radio, heading and paragraph, and keeping every field of a section in one renderer keeps the order the merchant set.

We rejected using the official API alone (simpler and safer across WooCommerce updates, but only 7 of the 10 types) because full parity on the Block checkout was an explicit goal.

*Amended 2026-11-13: originally the split was by field type (API for text/select/checkbox in every section, our block for the rest). Splitting by section instead avoids mixing two renderers in one section, which would have made the merchant's field order impossible to honour.*

## Consequences

- The Block field set's sections follow WooCommerce's locations: Contact, Address (one definition used for both the billing and the shipping form) and Additional. There is no separate Shipping section on the Block checkout, and a field cannot be limited to billing only or shipping only.
- Textarea, radio, heading and paragraph are not available in the Address section; the admin says so.
- Answers for API-backed fields and inner-block fields are stored and displayed by different code paths; display settings must behave the same for both where WooCommerce allows.
- Moving a section to the official API later (if WooCommerce adds the types) needs a data migration for existing orders, so it is a deliberate step.
