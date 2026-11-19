# ADR 0002: React + TypeScript SPA talking to a versioned REST API

- Status: Accepted (2026-11-18)
- Blueprint: [§8](../blueprint/08-system-architecture.md), [§12](../blueprint/12-api-specification.md)

## Context
The product needs a mobile-first web app and PWA now, with native apps and possibly partners later.

## Decision
- Frontend: React + TypeScript (Vite) SPA.
- Backend: an API-only `/api/v1` REST interface.
- The SPA and API are served from one origin.

## Alternatives
- **Inertia.js:** faster to build, but couples the UI to server routes and weakens API-first design.
- **Next.js:** adds a Node server to operate.

## Consequences
- An explicit API contract must be maintained. Generated OpenAPI and TS types keep it from drifting (ADR 0009).
- The same API can serve future native apps.

## Migration risk
Low.
