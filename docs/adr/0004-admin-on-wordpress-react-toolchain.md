# Admin built on WordPress's own React toolchain, with a custom look

The admin is a React app at WooCommerce → Checkout Fields with its own modern visual design. It is built with `@wordpress/scripts` and TypeScript, uses the copy of React that WordPress already ships, and has a small component layer of our own styled with CSS variables. `@wordpress/components` is used only for accessibility-heavy pieces such as the drawer, popovers and dropdown menus; `dnd-kit` handles drag-and-drop, including keyboard reordering. The admin talks to the server through our own REST routes under `checkout-fieldkit/v1`, restricted to users who can manage WooCommerce. Edits are held as a draft in the browser and saved together.

## Considered Options

- **Vite + Tailwind + a component kit (such as shadcn)**: faster to style, but bundles a second React, adds a few hundred kilobytes, and its global styles clash with wp-admin and draw Plugin Check warnings.
- **Stock WordPress components and styling throughout**: quickest and blends in, but would not deliver the improved, modern design that is a goal of the project.
- **Classic PHP settings pages, like the reference plugin**: rejected; its page-reload-per-action editing is one of the things we are improving on.
