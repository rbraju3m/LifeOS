# 22. Phase-by-phase Claude Code Implementation Plan

Each slice follows §17 of the brief:
1. Objective, files, DB, API, security and test plan are presented.
2. Wait for approval.
3. Implement only that slice.
4. Run tests and report actual results.
5. Commit only on explicit approval.
Each slice is independently testable.

**Phase 0 — Foundation**
- **0.1 Docs:**
  - Copy this blueprint into `docs/blueprint/` (one file per section) and add `docs/adr/0001…0011` for decisions D1–D11.
  - Update `CLAUDE.md` with the real commands.
  - Add `.idea/` to `.gitignore`.
- **0.2 Monorepo skeleton:**
  - `backend/` (Laravel 13, PHP 8.4), `frontend/` (Vite React TS), `deploy/`, `docker-compose.yml`, `Makefile`, `.editorconfig`.
  - Tests: `php artisan test` and `npm test` both green on the skeleton.
- **0.3 Quality tooling:** Pint, Larastan L8, Pest + arch tests, Deptrac config with module layers, ESLint/Prettier/tsc, Vitest, Playwright scaffold, a local CI script (`make ci`). A GitHub Actions workflow is prepared but only used once a remote exists.
- **0.4 Module infrastructure:**
  - `app/Modules/Shared` (HasUlids base, API error handler with problem+json, cursor pagination helper, request-ID middleware, `LocalDateTime`/`Money` value objects).
  - Module provider registration.
  - Tests: error format, request ID.

**Phase 1 — LifeOS MVP (backend slice first, then the matching UI slice)**
- **1.1 Identity backend:** users, user_preferences, consents migrations; Fortify headless; Sanctum SPA; argon2id; rate limiters; `/me`. Tests: auth flows, throttling, enumeration, CSRF.
- **1.2 Spaces + Entitlements backend:** `SpaceOwned` trait + `visibleTo` scope (moved here from 0.4: it needs the spaces tables and belongs in the Spaces module); spaces, space_members, space_invitations (tables only), plans/features/plan_features/subscriptions/overrides with a seeder (free plan); personal space created at registration; `SpaceAccess`, `RoleMatrix`, `SpaceContentPolicy`; `Entitlements` service. Tests: one personal space per user, the role matrix, entitlement precedence, "export always allowed".
- **1.3 Audit + security events:** audit_logs, listeners for auth events, `/me/security-events`, log scrubber. Tests.
- **1.4 Frontend shell:**
  - Design tokens, layout (mobile bottom nav: Today, Tasks, Calendar, Notes, More), i18n bn/en, auth pages, settings/profile.
  - API client generated from OpenAPI; TanStack Query; error and empty states; PWA manifest + service worker (app shell cache only).
  - Playwright J1.
- **1.5 Tasks:** backend (lists, tasks, tags, recurrence complete-to-generate) and UI (quick-add, list views, filters). **Isolation matrix infrastructure is introduced here** and reused for every later slice.
- **1.6 Notifications core:** notifications, deliveries, preferences, push_subscriptions; email channel; web push channel; inbox UI; preferences UI; service worker push handling.
- **1.7 Reminder engine:** reminders + occurrences; RecurrenceCalculator; dispatcher command; delivery job; snooze/dismiss; registry + Task source; inline reminders on tasks UI. Tests: the full queue/scheduler suite (§19), including concurrency and outage catch-up.
- **1.8 Calendar & important dates:** events + exceptions with expand-on-read, important dates, reminder sources, agenda endpoint, month/week/agenda UI.
- **1.9 Files:** files table, S3 private disk (MinIO locally), upload/validation/EXIF strip/ClamAV job, presigned download, quota. File access tests.
- **1.10 Notes:** notes, attachments, tags, FULLTEXT search, markdown editor (sanitized render).
- **1.11 Records:** records, fields, attachments, encryption, reveal with step-up + audit, expiry reminder defaults, UI with masking.
- **1.12 Dashboard + global search:** DashboardProvider/SearchProvider contracts per module, `/me/dashboard`, `/me/search`, Today UI with empty states and quick-add.
- **1.13 Privacy:**
  - Trash UI + purge job, data export (ZIP of JSON per module plus files, via the `ExportsUserData` contract).
  - Account deletion with grace + purge (`PurgesUserData`).
  - Tests: the export contains all data of the user and none of anyone else's.
- **1.14 Ops:**
  - Health endpoints, Horizon config, scheduler heartbeat, Sentry, deploy scripts (Deployer), server bootstrap script, backup/restore scripts and runbook, staging deploy.
  - **Production deploy only with explicit approval.**
- **1.15 Hardening:** ZAP scan, k6, axe audit, bn translation review, restore drill, closed beta checklist.

**Phases 2–6** reuse the same slice template:
1. Final schema review of the provisional tables (§10.9) with ADR updates.
2. Backend module with Reminder/Dashboard/Search/Export providers.
3. Isolation tests.
4. UI.
5. Entitlement seeds.
