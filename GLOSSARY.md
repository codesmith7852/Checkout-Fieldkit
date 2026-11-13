# Checkout FieldKit

A WooCommerce plugin that lets a store owner change which fields shoppers fill in at checkout: edit, hide, reorder and add fields, and choose where the answers show up afterwards.

## Language

### Checkout

**Checkout type**:
Which of WooCommerce's two checkout implementations a store uses: the Classic checkout or the Block checkout.
_Avoid_: checkout mode, checkout version

**Classic checkout**:
The checkout rendered by the `[woocommerce_checkout]` shortcode and its PHP templates.
_Avoid_: legacy checkout, shortcode checkout

**Block checkout**:
The checkout rendered by WooCommerce's Checkout block in the block editor. WooCommerce's default for new stores.
_Avoid_: Gutenberg checkout, new checkout

**Active checkout type**:
The checkout type the store's designated Checkout page actually uses right now.

### Fields

**Field set**:
All the fields configured for one checkout type. A store has exactly two field sets, one per checkout type, and they are edited independently.
_Avoid_: form, field group, layout

**Section**:
A named group of fields inside a field set that WooCommerce renders together. Classic checkout has Billing, Shipping and Additional; Block checkout has Contact, Address, Order and Additional Information.
_Avoid_: tab, area, fieldset

**Field**:
One input (or one piece of display-only content) inside a section, with its settings.
_Avoid_: input, element, control

**Built-in field**:
A field WooCommerce itself defines, such as billing email or shipping postcode. It can be changed or disabled but never deleted.
_Avoid_: default field, core field, standard field

**Custom field**:
A field the store owner added. It can be deleted.
_Avoid_: extra field, additional field, user field

**Critical field**:
A built-in field that WooCommerce depends on to take, ship, or tax an order, such as billing email or country. Disabling it or making it optional needs an explicit confirmation.
_Avoid_: required field (that means something else), protected field

**Field type**:
The kind of input a field is, such as text, select or date. Each checkout type supports its own list of field types.
_Avoid_: input type, control type

**Display field**:
A field that shows content but collects no value, such as a heading or paragraph.
_Avoid_: static field, content field

**Field name**:
The permanent identifier of a field, under which its value is stored on the order. Fixed once orders may have used it.
_Avoid_: key, slug, ID, meta key

### Behaviour

**Validation**:
A rule a field's value must satisfy before the order can be placed, such as "must be an email address".
_Avoid_: check, rule, constraint

**Display location**:
A place outside the checkout where a custom field's saved value can be shown: order confirmation page, customer's order view, admin order screen, and emails.
_Avoid_: visibility, output

**Locale override**:
A store setting that keeps the plugin's settings for address fields instead of letting WooCommerce replace them with country-specific labels, placeholders, order or required status.
_Avoid_: country override, address override

**Draft**:
Edits made in the admin that are not yet saved. Nothing reaches the checkout until the draft is saved.
_Avoid_: pending changes, unsaved state

**Reset**:
Returning one field set to WooCommerce's original built-in fields, discarding all edits and custom fields in it.
_Avoid_: restore defaults, revert

### Project

**Reference plugin**:
"Checkout Field Editor (Checkout Manager) for WooCommerce" by ThemeHigh, whose behaviour this plugin is modelled on. None of its code is used.
_Avoid_: original plugin, the competitor, ThemeHigh plugin

**Behaviour ledger**:
The plain-language record of how the reference plugin behaves, with the evidence for each statement. It is the only description of the reference plugin that implementers read.
_Avoid_: spec, analysis, notes

**Pro backlog**:
Features held back for a future paid add-on.
_Avoid_: roadmap, premium list
