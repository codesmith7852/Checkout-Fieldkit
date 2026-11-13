# Free version first; Pro parked; no importer from the reference plugin

The first release is the free plugin only, at feature parity with the reference plugin's free version (minus its cross-promotion tab), on both checkout types. Everything else is parked:

- **Pro**: what goes into it and how it is packaged are undecided. The free plugin exposes WordPress actions and filters at its seams (field types, sections, rendering, validation, saving) so a future add-on can extend it without rewriting it. Features beyond parity, including several the admin design would otherwise have had, are recorded in `docs/pro-backlog.md` instead of being built.
- **Importer**: we will not read or import the reference plugin's saved settings. Switching stores rebuild their form by hand.

Basic accessibility (keyboard use, screen-reader labels, visible focus, keyboard reordering) is a quality bar of the free plugin, not a feature, and is not held back for Pro.
