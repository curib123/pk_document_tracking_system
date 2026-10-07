# PK DTS Code Style

Keep the code simple, readable, and easy to maintain.

## General

- Use spaces, never tabs.
- PHP uses 4 spaces.
- JavaScript uses 2 spaces.
- Python uses 4 spaces.
- Keep one responsibility per function.
- Prefer descriptive names over short abbreviations.
- Break long query-builder chains across lines.
- Avoid dense one-line conditions when the branch matters to business logic.
- Keep IDs internal. Frontend text should use readable names, references, titles, or codes.

## Comment guide

Comments should explain **why** the code exists or what business rule it protects. Do not comment obvious syntax.

Use short modern Boholano/Cebuano + English comments naturally, for example:

```php
// Ari ta mag-lock sa record first para dili ma-overwrite ang newer update.
```

```php
// Kani nga snapshot mao gihapon gamiton sa old request bisan ma-change ang default workflow.
```

```js
// Diri ta mag-sync sa button preset para sakto gyud ang request type pag-open sa modal.
```

```js
// ID internal ra ni; readable name ang ipakita sa user para dili confusing.
```

```python
# Ari ta mag-simulate sa API para UI behavior ra atong gi-test, dili real data.
```

Keep comments professional and short. Use them around validation, transactions, permissions, workflow routing, state synchronization, migrations, and other logic that is not obvious at first glance.
