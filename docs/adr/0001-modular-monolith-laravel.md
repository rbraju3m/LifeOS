# ADR 0001: Modular monolith on Laravel 13 / PHP 8.4

- Status: Accepted (2026-11-18)
- Blueprint: [§8](../blueprint/08-system-architecture.md), [§9](../blueprint/09-domain-and-module-architecture.md)

## Context
LifeOS will grow into five product domains (Life, Obligations, Assets, Home, Family) and will be built by a small team. Operations must stay cheap, and the code must be easy to change.

## Decision
Build one Laravel 13.x application on PHP 8.4, organised as a modular monolith. It has one deployable and one database transaction boundary.

## Alternatives
- **Microservices:** too much operations overhead, and no demonstrated scaling need.
- **NestJS/Node:** one language across frontend and backend, but less built in (auth, queues, scheduling, notifications, policies).
- **Django:** comparable, but weaker fit with the team and PHP hosting.

## Consequences
- Laravel already ships auth, queues, scheduler, notifications and policies.
- Hosting is cheap.
- All modules share one runtime, so boundaries must be enforced by tooling (see ADR 0003).

## Migration risk
Low. A module with clean contracts can be extracted into a service later if a real need appears.
