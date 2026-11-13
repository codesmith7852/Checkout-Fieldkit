# Two-step clean room: analyse the reference plugin, implement only from the behaviour ledger

Checkout FieldKit is modelled on the behaviour of the reference plugin, which is GPL-licensed and ships as readable PHP and JavaScript. Reading it is permitted, but we want our code to be demonstrably our own, so the work is split in two. An analysis session reads the reference plugin's source and runs it on a test site, then writes `docs/reference-behaviour.md`: plain-language statements of what it does, each with its evidence and any unknowns, and no code, strings, CSS or assets from it. Implementation agents work only from that ledger, the glossary and these ADRs, and never see the reference plugin's source.

## Considered Options

- **Behaviour-only study** (run it and read its docs, never its source): slower, and leaves gaps in edge-case behaviour such as validation details and where order data is stored.
- **One session reads and writes**: fastest, but blurs the line between studying their code and writing ours.

## Consequences

- When an implementer needs a behaviour that the ledger does not cover, the question goes back to the analysis side and the answer is added to the ledger; the implementer does not go and look.
- Copying user-facing strings is also out: labels, help text and messages are written fresh.
