# Block checkout fields: WooCommerce's official API where it fits, our own inner block for the rest

The Block checkout offers 10 field types for parity with the reference plugin. WooCommerce's official additional-checkout-fields API (as of WooCommerce 11.3) supports only text, select, checkbox and date, in three locations: contact, address and order. So:

- **Official API**: text, select and checkbox, plus email, URL and number registered as text fields with input attributes and validation. WooCommerce then stores the answers and shows them on the admin order screen, in emails and in the customer's account itself.
- **Our own checkout inner block**: textarea, radio, heading and paragraph, which the API cannot express. Their values travel with the order through a Store API extension and are saved and displayed by our code.

We rejected using the official API alone (simpler and safer across WooCommerce updates, but only 7 of the 10 types) because full parity on the Block checkout was an explicit goal.

## Consequences

- The Block field set's sections follow WooCommerce's locations: Contact (email only), Address (one definition used for both the billing and the shipping form) and Additional (the "order" location). There is no separate Shipping section on the Block checkout, and a field cannot be limited to billing only or shipping only.
- Answers for API-backed fields and inner-block fields are stored and displayed by different code paths; display settings must behave the same for both.
- When WooCommerce's API gains a type we render ourselves, moving that type to the API needs a data migration for existing orders, so it is a deliberate step, not automatic.
