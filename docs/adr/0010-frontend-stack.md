# ADR 0010: Frontend stack

- Status: Accepted (2026-11-18)
- Blueprint: [§8](../blueprint/08-system-architecture.md), [§19](../blueprint/19-testing-strategy.md)

## Decision
| Concern | Choice |
|---|---|
| UI framework | React 19, TypeScript in strict mode, built with Vite |
| Routing | React Router |
| Server state | TanStack Query (no Redux) |
| Forms | react-hook-form + zod |
| Translation | i18next (bn/en) |
| Styling | Tailwind CSS + Radix UI primitives, design tokens as CSS variables |
| PWA | vite-plugin-pwa (Workbox) |
| Testing | Vitest + Testing Library + MSW; Playwright for end-to-end |

## Alternatives
- Redux / Zustand for state management.
- MUI or Chakra for components (heavier bundles; harder to localise and theme).

## Consequences
Small bundle, accessible primitives and one approach to server state.

## Migration risk
Low.
