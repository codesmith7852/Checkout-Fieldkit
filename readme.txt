=== Checkout FieldKit for WooCommerce ===
Contributors: codesmith7852
Tags: woocommerce, checkout, checkout fields, block checkout, custom fields
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Edit, hide, reorder and add WooCommerce checkout fields on both the Classic and the Block checkout.

== Description ==

Checkout FieldKit lets you decide which fields shoppers fill in at checkout.

* Two independent field sets: one for the Classic checkout and one for the Block checkout.
* Change the label, placeholder, default value, validation, required and enabled state of WooCommerce's own fields.
* Add custom fields and choose where the answers appear afterwards: the order confirmation page, the customer's account, the admin order screen and emails.
* Reorder fields by drag and drop or from the keyboard.
* Keep your address field settings from being replaced by WooCommerce's country-specific defaults.
* Export, import and reset your settings.

Compatible with High-Performance Order Storage. Requires WooCommerce 9.0 or newer.

This plugin is in early development.

== Installation ==

1. Install and activate WooCommerce.
2. Upload the plugin to the `/wp-content/plugins/checkout-fieldkit` directory, or install it from the Plugins screen.
3. Activate the plugin through the Plugins screen.

== Frequently Asked Questions ==

= Does it work with the Block checkout? =

Yes. The Classic and the Block checkout each have their own set of fields.

= What happens to order data when I delete the plugin? =

Deleting the plugin removes its settings. Answers shoppers gave on existing orders stay on those orders.

== Changelog ==

= 0.1.0 =
* Plugin skeleton: requirement checks and WooCommerce compatibility declarations.
