# Checkout FieldKit for WooCommerce

Edit, hide, reorder and add WooCommerce checkout fields, on both the Classic and the Block checkout, from a modern React admin.

> **Status:** in development. The plugin skeleton and tooling are in place; features follow.

## What it does

- Two independent field sets, one for the Classic checkout and one for the Block checkout. The admin opens on the one your store actually uses.
- Change the label, placeholder, default value, validation, required and enabled state of WooCommerce's built-in fields.
- Add custom fields of many types and choose where their values appear afterwards: the order confirmation page, the customer's account, the admin order screen and emails.
- Reorder fields by drag and drop or from the keyboard.
- Keep your address field settings from being replaced by WooCommerce's country-specific defaults.
- Export, import and reset your settings.

## Requirements

WordPress 6.6+, WooCommerce 9.0+, PHP 7.4+. Compatible with High-Performance Order Storage.

## Development

You need PHP 7.4 or newer (the team develops on 8.5), Composer, Node.js 22.22.2+ with npm, MySQL, and WP-CLI. Work inside a local WordPress site with WooCommerce, with this repository at `wp-content/plugins/checkout-fieldkit`.

```sh
composer install
npm install
```

### Test database

The PHP tests run against a real WordPress and WooCommerce in a database of their own. **Every run deletes all tables with the test prefix**, so give the tests an empty database that nothing else uses, and use a different one from other developers:

```sh
mysql -u root -p -e "CREATE DATABASE cfk_tests_yourname"
cp tests/php/local-config.sample.php tests/php/local-config.php   # git-ignored; put your database name and password here
```

Each setting can also come from an environment variable of the same name, which wins over the file:

| Setting | Default | |
|---|---|---|
| `CFK_TEST_DB_NAME` | `cfk_tests` | Test database |
| `CFK_TEST_DB_USER`, `CFK_TEST_DB_PASSWORD`, `CFK_TEST_DB_HOST` | `root`, empty, `localhost` | |
| `CFK_TEST_TABLE_PREFIX` | `wptests_` | |
| `CFK_WP_CORE_DIR` | the site this plugin is in | WordPress files to load |
| `CFK_WC_DIR` | `../woocommerce` next to this plugin | WooCommerce to load |
| `CFK_TEST_HPOS` | `1` | Start each test with High-Performance Order Storage on (`1`) or off (`0`) |

Uploads and WooCommerce logs written during tests go to `<system temp>/cfk-tests-<database>/`, not to the site.

### Commands

| Command | What it does |
|---|---|
| `composer test` | PHPUnit with WordPress and WooCommerce loaded |
| `composer test -- tests/php/Integration/PluginTest.php` | One test file |
| `composer test -- --filter test_name` | Tests whose name matches |
| `CFK_TEST_HPOS=0 composer test` | Same tests with orders stored as posts |
| `composer test:multisite` | Same tests on a multisite network, including the `ms-required` group |
| `composer lint` / `composer lint:fix` | PHPCS: WordPress Coding Standards and PHP 7.4 compatibility |
| `npm test` | Jest with Testing Library (`npm test -- tests/js/admin` for one folder) |
| `npm run build` / `npm start` | Build the admin and checkout block scripts into `build/` once / on every change |
| `npm run lint` | ESLint, stylelint and the TypeScript type check |
| `npm run zip` | Build, then pack `dist/checkout-fieldkit.zip` without anything listed in `.distignore` |
| `npm run plugin-check` | `npm run zip`, then Plugin Check on the zip's contents |

`wp plugin check checkout-fieldkit` on the repository folder also reports development files that never ship (`.editorconfig`, `phpunit.xml.dist`, `tests/`, `dist/`); `npm run plugin-check` checks what is actually released.

### Where things go

- `checkout-fieldkit.php`: plugin header and start-up only.
- `includes/`: PHP classes in the `CheckoutFieldKit` namespace, one class per file, PSR-4 style (`CheckoutFieldKit\Admin\Page` is `includes/Admin/Page.php`). No Composer packages at runtime.
- A feature is a class implementing `CheckoutFieldKit\Service` (it adds its hooks in `register_hooks()`), listed in `Plugin::default_services()`. Nothing loads unless WooCommerce 9.0+ is active.
- The domain core (no hooks of its own) is shared through `Plugin::instance()->domain()`:
  - `includes/Fields/`: `Sections`, `FieldTypes` (types per checkout type and section, the settings that apply to each, validation choices; `describe()` serialises them for the admin), `Field` (a field's keys and defaults), `FieldNormalizer`, `FieldSetValidator` (every rule of spec §2.5, all problems at once), `DefaultFields` (WooCommerce's built-in fields) and `EssentialFields`.
  - `includes/Storage/`: `FieldSetRepository` (one per checkout type) and `SettingsRepository`, each with a schema `VERSION` and a `Migrator`.
  - `includes/Tools/ImportExport.php`: export document, import validation, preview and import.
- `includes/Orders/`: custom field values on orders, for both checkout types (no hooks of their own unless noted):
  - `ValueSanitizer::sanitize( $field, $raw, $checkout_type )`: the string to store for a submitted value (spec §4.1); `""` means store nothing.
  - `ValueValidator::problem( $field, $raw, $checkout_type, $country )` / `problems( $fields, $values, … )`: what is wrong with a submitted value (required, the type's own check, the chosen validations, options), as a message naming the field; `""` when fine. `src/checkout/validation.ts` mirrors it in the browser.
  - `FieldValues`: the reusable display API. `meta_key( $name, $checkout_type )` says where a value lives (classic `billing_*`/`shipping_*` → `_<name>`, saved by WooCommerce; everything else `<name>`), `stored( $order, $field, $checkout_type )` reads it, `format( $field, $value, $checkout_type, FieldValues::HTML|PLAIN, $order )` shows it (option labels, `Yes`, line breaks, escaped for HTML), `entries( $order, $fields, $checkout_type, $format )` gives label/value rows for a list of fields, and `checkout_type_of( $order )` tells which checkout placed an order. Create it with `new FieldValues( Plugin::instance()->domain()->field_types() )`.
  - `ValueDisplay` (service): shows one field set's values in emails, on the thank-you and My Account order pages and on the admin order screen (spec §4.2). Registered for the classic field set.
- `includes/Classic/OrderValueSaver.php` (service): saves classic checkout values on the order and customer.
- `includes/Block/`: the Block checkout. `AddressFields` and `BuiltInAddressFields` (Address section, WooCommerce's API); for Contact and Additional, our own inner blocks:
  - `InnerBlockFields`: the enabled custom fields of Contact and Additional, and the data the browser gets (`getSetting( 'checkout-fieldkit_data' )`).
  - `CheckoutBlocks` (service): registers `CheckoutIntegration` (our scripts) with the Checkout block, the block types `checkout-fieldkit/contact-fields` and `checkout-fieldkit/additional-fields`, and adds the Additional block to rendered Checkout pages that lack it.
  - `OrderValues` (service): the Store API extension `checkout-fieldkit` (schema, validation, saving as order meta `<name>`).
  - `OrderValueDisplay` (service): `Orders\ValueDisplay` for the Block field set.
- Any filter we add to WooCommerce's checkout field or country locale hooks must return its input unchanged while `DefaultFields::is_computing()` is true, so our changes never end up in the defaults and reading a field set inside the filter cannot recurse.
- `includes/Classic/`: the Classic checkout. `CheckoutFields` applies the classic field set and the locale overrides to WooCommerce's billing, shipping and order fields (also used by My Account → Addresses), `FieldRenderer` renders the custom types WooCommerce cannot, `CheckoutValidation` checks number, url and choice values.
- `src/`: TypeScript and CSS for the admin, built to `build/` by `@wordpress/scripts` (entries in `webpack.config.js`).
- `src/classic-checkout/`: the Classic checkout's small script (locale overrides after a country change, enhanced multi-selects) and styles. The PHP loads them from `build/` when built.
- `src/`: TypeScript and CSS for the admin (`src/admin/`) and the checkout inner blocks (`src/checkout/`: `frontend.tsx`, `editor.tsx`, block.json files), built to `build/` by `@wordpress/scripts` with WooCommerce's dependency extraction, so `@woocommerce/*` imports become WooCommerce's script globals (entries in `webpack.config.js`; types in `src/types/woocommerce.d.ts`). Jest maps those packages to the doubles in `tests/js/support/woocommerce/`.
- `tests/php/`: PHPUnit tests; extend `CheckoutFieldKit\Tests\TestCase`, which gives you `create_order()`, `create_product()`, `enable_hpos()`, `disable_hpos()`, `log_in_as()` and the `data_order_storage` provider for running a test with HPOS on and off.
- `tests/js/`: Jest tests, mirroring `src/`.

## Project docs

- [GLOSSARY.md](GLOSSARY.md): the words we use and what they mean.
- [docs/adr/](docs/adr/): decisions and why we made them.
- [docs/pro-backlog.md](docs/pro-backlog.md): features held back for a future Pro add-on.

None of these are included in the release zip; see `.distignore`.

## How work happens

Each task is a GitHub issue with acceptance criteria. It is built on its own branch and opened as a pull request that closes the issue. Pull requests are merged by the maintainer after review.

## Licence

GPL-2.0-or-later. See [LICENSE](LICENSE).
