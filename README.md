# Checkout FieldKit for WooCommerce

Edit, hide, reorder and add WooCommerce checkout fields, on both the Classic and the Block checkout, from a modern React admin.

> **Status:** in design. No plugin code yet.

## What it does

- Two independent field sets, one for the Classic checkout and one for the Block checkout. The admin opens on the one your store actually uses.
- Change the label, placeholder, default value, validation, required and enabled state of WooCommerce's built-in fields.
- Add custom fields of many types and choose where their values appear afterwards: the order confirmation page, the customer's account, the admin order screen and emails.
- Reorder fields by drag and drop or from the keyboard.
- Keep your address field settings from being replaced by WooCommerce's country-specific defaults.
- Export, import and reset your settings.

## Requirements

WordPress 6.6+, WooCommerce 9.0+, PHP 7.4+. Compatible with High-Performance Order Storage.

## Project docs

- [GLOSSARY.md](GLOSSARY.md): the words we use and what they mean.
- [docs/adr/](docs/adr/): decisions and why we made them.
- [docs/pro-backlog.md](docs/pro-backlog.md): features held back for a future Pro add-on.

None of these are included in the release zip; see `.distignore`.

## How work happens

Each task is a GitHub issue with acceptance criteria. It is built on its own branch and opened as a pull request that closes the issue. Pull requests are merged by the maintainer after review.

## Licence

GPL-2.0-or-later. See [LICENSE](LICENSE).
