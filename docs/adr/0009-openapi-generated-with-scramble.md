# ADR 0009: OpenAPI generated from code (Scramble), TS types generated from the spec

- Status: Accepted (2026-11-18)
- Blueprint: [§12.8](../blueprint/12-api-specification.md)

## Decision
- `dedoc/scramble` generates `docs/openapi/v1.yaml` from the backend code, and the generated spec is committed.
- CI fails if the committed spec differs from a fresh generation.
- The frontend generates its types with `openapi-typescript`.

## Alternatives
**Spec-first, hand-written:** tends to drift from the implementation.

## Consequences
Some annotations are needed so the generated spec is accurate.

## Migration risk
Low. The output is a standard OpenAPI document.
