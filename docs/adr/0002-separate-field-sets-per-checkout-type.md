# Separate field sets for Classic and Block checkout

A store has two field sets, one per checkout type, edited independently. The admin detects the active checkout type, opens on that field set, and marks the other "not in use on this store". A "copy to the other checkout" action carries a field across when its field type is supported on both.

We rejected a single unified field list that renders to both checkouts. Block checkout supports far fewer field types and gives much less control over the built-in address fields, so a unified list would be full of "doesn't apply here" exceptions, and a change made for one checkout would silently alter the other.

## Consequences

- Each field set has its own sections, its own list of allowed field types, and its own reset.
- A store that switches checkout type must review the other field set; the admin should make that obvious rather than migrate automatically.
