# 19. Testing Strategy

| Layer | Tool | Scope |
|---|---|---|
| Unit (PHP) | Pest | value objects, RecurrenceCalculator (DST, leap day, month-end, COUNT/UNTIL), entitlement resolution, RoleMatrix, scrubbers |
| Feature/API | Pest + Laravel HTTP tests on **MySQL 8.4** (not SQLite: we need generated columns, FULLTEXT and SKIP LOCKED) | every endpoint: happy path, validation, 401/403/404, rate limits, envelope/OpenAPI conformance (`spectator` or schema assertions) |
| **Authorization isolation matrix** | Pest datasets | For **every** route that takes an ID: user A's resource accessed by (B stranger, B in the same family as A with the item private, viewer, child, removed member, unauthenticated) → expected 404/403/401. A route-discovery test fails CI if a new route is not registered in the matrix |
| Permission tests | Pest | role matrix × abilities in family spaces; admin cannot see private items; leaving moves private items |
| Queue/scheduler | Pest with `Queue::fake`, `Bus::fake`, real DB; `travelTo()` | dispatcher idempotency (run twice → one occurrence), concurrent dispatchers (two processes, SKIP LOCKED), outage catch-up, snooze, version mismatch, retries/backoff, quiet hours |
| Notification | `Notification::fake`, Mail fake, Web Push mocked client | channel selection ∩ prefs ∩ entitlements; 410 removes subscription; dedupe |
| File access | `Storage::fake('s3_private')` + MinIO in an integration job | MIME spoofing, oversize, quota, presigned URL only for authorized users, deleted file → 404, quarantine blocks download |
| Migrations | CI job | `migrate:fresh` → `migrate:rollback` → `migrate` on MySQL; schema dump diff; seeders are idempotent |
| Architecture | Pest `arch()` + Deptrac | module dependency rules; no `env()` outside config; controllers do not use Eloquent `find` without a scope; no `dd/dump` |
| Static analysis | PHPStan (Larastan) level 8, Pint; ESLint, `tsc --strict`, Prettier | |
| Frontend | Vitest + Testing Library + MSW | components, forms, i18n keys complete (bn/en parity test), a11y with `jest-axe` |
| E2E | Playwright (mobile viewport + desktop) | J1–J7 journeys against a seeded stack (Docker Compose) |
| Security | `composer audit`, `npm audit`, OWASP ZAP baseline scan against staging before beta | |
| Performance | k6 script for dashboard/agenda/tasks at 50 rps; dispatcher with 100k reminders due | before beta |

Merge gate: all of the above except ZAP and k6 run in CI on every change. Coverage of at least 80% for `Modules/*/Actions`, `Policies` and `Reminders`.
