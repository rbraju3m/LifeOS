# ADR 0005: ULID primary keys (CHAR(26), ascii_bin)

- Status: Accepted (2026-11-18)
- Blueprint: [§10.0](../blueprint/10-database-schema.md)

## Decision
All domain tables use an application-generated ULID as the primary key (Laravel `HasUlids`).

## Why
- Public IDs cannot be enumerated, which removes guessable-ID attacks.
- ULIDs are time-ordered, so B-tree inserts stay efficient.
- They allow client-generated IDs later (offline or idempotent creates).
- One ID serves both internal and external use.

## Alternatives
- **BIGINT plus a separate public UUID:** two identifiers to manage.
- **UUIDv4:** random inserts fragment the index.
- **BIGINT only:** IDs can be enumerated.

## Consequences
Keys and indexes are larger (26 bytes vs 8). This is acceptable below about 100M rows per table.

## Migration risk
Low.
