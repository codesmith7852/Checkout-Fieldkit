# Built-in fields can be disabled, never deleted; critical fields need confirmation

The reference plugin lets any field, including WooCommerce's own, be removed outright with no warning. We deliberately do less: built-in fields can be edited or disabled but not deleted, and disabling a critical field or making it optional asks for an explicit confirmation that says what will stop working. Custom fields can be deleted freely, with a short undo.

Disabling has the same effect on the checkout as deleting, but it is reversible and keeps the field's settings. The most common support problem for this kind of plugin is a store that stopped taking orders, shipping or charging tax correctly after someone removed a field WooCommerce needed.
