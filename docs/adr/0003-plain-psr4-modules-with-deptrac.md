# ADR 0003: Plain PSR-4 modules with boundaries enforced by Deptrac

- Status: Accepted (2026-11-18)
- Blueprint: [§9](../blueprint/09-domain-and-module-architecture.md)

## Context
The monolith needs clear domain boundaries without locking into a third-party framework.

## Decision
- Each module lives in `backend/app/Modules/<Module>` with its own ServiceProvider, registered in `bootstrap/providers.php`.
- Modules are layered as Kernel (Shared) → Platform → Feature → Composition.
- Feature modules must not depend on other Feature modules.
- Platform modules must not depend on Feature modules. Where a Platform module needs to know about features, features register themselves with it (for example, the reminder source registry).
- Deptrac and Pest `arch()` tests enforce these rules in CI.

## Alternatives
- **`nwidart/laravel-modules`:** offers generators, but is opinionated and sensitive to Laravel upgrades.
- **Separate Composer packages:** too heavy for this stage.

## Consequences
More manual wiring is needed, but there is no package lock-in.

## Migration risk
Low.
