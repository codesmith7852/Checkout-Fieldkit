# Settings stored as versioned WordPress options

Each field set and the general settings are stored as their own WordPress option, and every stored value carries a schema version number. On load, older versions are migrated forward in code before use. We chose options over custom database tables because the data is small, read on every checkout, and benefits from WordPress's option caching. The version number is there because the stored shape will change as features are added, and a store may skip several releases between updates.

Values shoppers enter are stored on the order (and, where chosen, on the customer) through WooCommerce's own order and customer data APIs, never with direct post-meta calls, so they work with High-Performance Order Storage.
