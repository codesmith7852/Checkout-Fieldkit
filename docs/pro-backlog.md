# Pro backlog

Features held back from the free plugin for a future paid add-on (see [ADR 0006](adr/0006-free-first-pro-parked.md)). Nothing here is committed to; it is a list to choose from when Pro is planned. This file is excluded from the release zip.

## Admin editing improvements

Proposed during design as improvements beyond the reference plugin's free version, and moved here rather than built:

- **Field width as a choice**: full, half-left or half-right, picked from a control instead of typing WooCommerce's layout class names by hand.
- **Duplicate field**: copy an existing field as the starting point for a new one.
- **Search and filter** in the field list by name, label, field type, or enabled state.
- **Undo and redo** for edits made in the current draft.

## Larger ideas

- **Visual on-checkout editor**: rearrange and edit fields by dragging them on a live preview of the actual checkout page.
- **Field templates**: ready-made fields such as "gift message" or "delivery date" that can be added in one click.

## What the reference plugin charges for

For comparison only; the split for our Pro version is undecided. From the reference plugin's public listing:

- Conditional display of fields and sections (cart contents, totals, user role, product, category, other field values, shipping or payment method).
- Price fields that add a fee (fixed, customer-entered, percentage of cart or subtotal, per unit).
- Custom validation rules and "confirm" fields that must match another field.
- Extra field types: file upload, date picker, time picker, label.
- Custom sections at 14 positions on the checkout.
- Repeating fields by product quantity or cart count.
- Editing the address display format.
