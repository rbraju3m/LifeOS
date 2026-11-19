# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Current state

LifeOS is a freemium consumer platform for managing personal and family life. It launches in Bangladesh first, then goes global. Phase 0 (foundation) is done. Phase 1 is in progress: slices 1.1 (Identity: accounts and login), 1.2 (Spaces and Entitlements), 1.3 (Audit log and log scrubbing) and 1.5a (content API groundwork and Tags) are built. 1.4 (frontend) waits for Node.js. The backend is a Laravel 13 API in `backend/`. `frontend/` (React) and Docker Compose are not set up yet, because this machine has no Node.js or Docker.

- `docs/blueprint/`: the approved implementation blueprint, one file per section (PRD, schema, API, security, roadmap, …). Start at `docs/blueprint/README.md`. Slices are listed in `22-phase-by-phase-claude-code-implementation-plan.md`.
- `docs/adr/`: architecture decision records. Supersede an ADR with a new one rather than editing an accepted one.
- Open product questions (sign-in methods, the scope of "records", hosting region, …) are in `docs/blueprint/00-context.md` §0.1. Until they are answered, the defaults stated there apply.

## Stack (see ADRs)

Laravel 13 / PHP 8.4+ modular monolith (`backend/app/Modules/<Module>`), MySQL 8.4 (8.0 locally), Redis via Predis (sessions, cache, queues), Sanctum SPA cookie auth + Fortify (headless), React + TypeScript + Vite SPA/PWA (`frontend/`, not yet created), private S3-compatible storage, single-VPS deployment.

## Architecture rules to preserve

- Every content row belongs to a **space** (`space_id`). A personal space has exactly one member, and a family is a shared space. Authorization is based on space membership, the member's role, the item's `visibility` and its `created_by`, and is always enforced in the backend (policies plus `visibleTo` query scopes). Never trust a client-supplied `space_id`, `created_by` or role.
- Requests for another user's resources return 404, not 403.
- Feature modules must not depend on other feature modules. Platform modules (Identity, Spaces, Entitlements, Files, Notifications, Reminders, Audit, Privacy, Tags) must not depend on feature modules; features register with them through registries or contracts.
- Prices and limits live in the entitlement tables, never in code. Export, deletion and access to existing data are never gated by plan.
- Store instants in UTC as `DATETIME(3)`. Store wall-clock intent with an IANA timezone. Store money as `*_amount_minor` plus `currency`.
- Create ULID columns only through `App\Modules\Shared\Database\Ulid` (CHAR(26) ascii_bin). Foreign keys need the same collation as the column they reference.

## Commands

Run from the repo root (each wraps a `composer` script in `backend/`):

```sh
make setup    # composer install, create .env, app key, migrate
make dev      # php artisan serve
make test     # Pest test suite (Feature, Unit, Architecture)
make lint     # Pint style check (make format to fix)
make analyse  # Larastan level 8
make deptrac  # module boundary check (backend/deptrac.yaml)
make ci       # all of the above + composer audit; must pass before any commit
cd backend && vendor/bin/pest --filter="signs in and returns the user"   # a single test
cd backend && php artisan queue:work                                     # run queued jobs (e.g. emails) locally
```

- Tests use Pest. Architecture rules are in `backend/tests/Architecture/ArchTest.php`.
- Module boundaries: each module in `deptrac.yaml` is its own layer, with an explicit allow-list. When adding a module or a dependency, update `deptrac.yaml` and the blueprint §9.1 table together.
- Do not silence PHPStan with ignores or baselines; fix the cause.
- No `public` filesystem disk: user files are only ever served via short-lived signed URLs.

## Local environment

- PHP 8.5 (`composer.json` requires `^8.4`), the system Composer 2.7, and MySQL 8.0 and Redis running natively.
- Development uses the `lifeos` database. Tests use `lifeos_test`, which `phpunit.xml` forces, and `tests/TestCase.php` refuses to run against any database whose name doesn't end in `_test`.
- The MySQL connection uses InnoDB, `utf8mb4_0900_ai_ci` and session time zone `+00:00`. `tests/Feature/DatabaseTest.php` checks all three.
- Credentials live only in the git-ignored `backend/.env`. Mail goes to the log locally (`MAIL_MAILER=log`).

## Shared module (`backend/app/Modules/Shared`)

- API errors: throw normal Laravel exceptions, or `ApiProblemException($status, $code, $detail, $meta)` for domain errors (e.g. `entitlement_exceeded`). `ProblemRenderer` turns them into RFC 9457 problem+json. Exception messages are never sent to clients, and every error code needs a title in both `lang/en/errors.php` and `lang/bn/errors.php` (a test checks they match).
- Every request gets an `X-Request-Id`, which is added to the log context and to error bodies.
- Domain models extend `BaseModel` (ULID keys, `DATETIME(3)` timestamps). Each new module gets a provider extending `ModuleServiceProvider`, placed in the module root and registered in `bootstrap/providers.php`. The provider auto-loads `Database/Migrations` and mounts `routes/api.php` under `/api/v1`.
- Request validation extends `StrictFormRequest`, which rejects unknown top-level fields with a 422. List `password_confirmation`-style fields in `$alsoAllowed`.
- Lists: `cursorPaginate(PerPage::fromRequest($request))`.
- POST creates take `Idempotency-Key`: add `->middleware(Idempotency::class)` to the route (after `auth:sanctum`). PATCH supports `If-Match`: load the row with `lockForUpdate()` in a transaction and call `Version::assertMatches($request, $row)`. Resources expose `'version' => Version::of($model)`. Money: `Money` (minor units). Wall-clock times: `LocalDateTime` (IANA zone; DST gap shifts forward, ambiguous times take the first occurrence).

## Identity module (`backend/app/Modules/Identity`)

- Fortify runs headless and its own routes are disabled (`Fortify::ignoreRoutes()`). `Identity/routes/api.php` mounts only the Fortify controllers we expose, under `/api/v1/auth`. Credential checks go through `Auth/AuthenticateUser` (email lockout, equal timing for unknown emails, suspended accounts).
- The SPA authenticates with Sanctum session cookies, so requests must come from a `SANCTUM_STATEFUL_DOMAINS` origin, and writes need the `X-XSRF-TOKEN` header (from `GET /sanctum/csrf-cookie`) or `Sec-Fetch-Site: same-origin`.
- `POST /auth/register` always returns the same 202 and never signs in, so an existing email is not revealed. `POST /auth/forgot-password` behaves the same way.
- Sensitive routes use the `RequireRecentPassword` middleware: a password confirmation within the last 15 min, otherwise 423 `password_confirmation_required`.
- `User` keeps explicit `$attributes` defaults for nullable columns, because strict mode rejects reading attributes a new model never loaded.
- `SetLocale` picks the user's locale, otherwise `Accept-Language` (bn/en). It is in the middleware priority list before authentication, so a 401 is also localised.

## Spaces module (`backend/app/Modules/Spaces`)

- Every user gets a personal space at registration: Identity dispatches `UserRegistered` inside the registration transaction, and `Listeners/CreatePersonalSpace` handles it. A generated unique column (`personal_owner_key`) makes a second personal space impossible.
- Content models implement `Contracts/SpaceOwnedContent` and use `Models/Concerns/BelongsToSpace`. List queries **must** use `->visibleTo($user)`. Policies extend `Authorization/SpaceContentPolicy`: items the user can't see are a 404, visible items they can't change are a 403.
- `Authorization/RoleMatrix` is the permission table from blueprint §14. `tests/Unit/Spaces/RoleMatrixTest.php` spells out the whole table, so any change shows up as a reviewed diff.
- `SpaceAccess` (membership and role lookup) is bound `scoped`, never `singleton`, so queue workers don't reuse stale memberships.
- Ownership columns (`type`, `owner_user_id`, `role`, `user_id`, …) are never mass-assignable. Set them in actions with `forceFill`.
- Nested content routes use `Route::scopeBindings()->prefix('spaces/{space}')`. Register the model with `SpaceContentRegistry::register('tag', Tag::class)` in the module provider; `Space::resolveChildRouteBinding` then finds the child only inside that space (404 otherwise).
- Content without a `visibility` column overrides `hasVisibilityColumn()` to return false (always space-wide).
- Authorize write requests in the FormRequest's `authorize()` (it runs before validation), so callers without access never get validation answers about another space.

## Entitlements module (`backend/app/Modules/Entitlements`)

- `Entitlements::for($space)` applies the first match of: unexpired override, live subscription's plan, default plan for the space type (`free` / `free_family`). Unlisted features resolve to false/0, a `null` limit means unlimited, and `export.enabled` is always true.
- In actions, call `Entitlements::assertCanCreate($space, FeatureCode::X, $currentCount)`, which returns a 403 `entitlement_exceeded`. Never compare plan names, and never hard-code limits. Use `FeatureCode` constants.
- The plan and feature catalogue is data (`Database/Seeders/EntitlementCatalogSeeder`, run by a migration). Results are cached per space for 10 min and dropped when that space's subscription or overrides change.

## Audit module (`backend/app/Modules/Audit`)

- `AuditLogger::record($action, $actor, $spaceId, $subjectType, $subjectId, $metadata)` is the only way to write `audit_logs` (an arch test enforces that only the Audit module uses `AuditLog`). It writes synchronously, so the entry commits with the caller's transaction, and takes IP/UA from the current request (none for console or queue). Actions match `area.verb_noun`. Metadata is flat scalars and refuses sensitive keys (`Shared/Logging/SensitiveKeys`).
- Rows are append-only: the model throws on update/delete. Only `audit:prune` (monthly; `auth.*` kept 730 days, others 365, `config/lifeos.php`) deletes them.
- `auth.*` actions are security events, listed at `GET /me/security-events`. `Listeners/RecordAuthEvents` maps Identity/Fortify events to them. Laravel's `Failed` event is never used (it carries the plaintext password); Identity dispatches `LoginFailed`/`AccountLocked` for existing accounts only.
- Every log channel has the `ScrubLogs` tap (`Shared/Logging`), which redacts sensitive keys and bearer/basic credentials. Add it to any new channel.

## Tags module (`backend/app/Modules/Tags`)

- Platform module (blueprint §9.1): feature modules link to tags through their own pivots (`task_tag`, …) and must check that tag and item share a space.
- Tags are space-wide (no `visibility`) and hard-deleted. Names are unique per space, case- and accent-insensitive (column collation). RoleMatrix content type `tag`: children and viewers cannot create; members edit or delete only their own.

## Test conventions

- Feature tests send `Origin: APP_URL` automatically (`tests/Pest.php`), so Sanctum treats them like SPA requests. They may not make network calls (`Http::preventStrayRequests()`).
- The breached-password API is faked as "not breached"; call `Tests\Support\FakePwnedPasswords::breach($password)` to simulate a breached password.
- `Database\Factories\UserFactory::PASSWORD` is the plain-text password of every factory user.
- Laravel skips CSRF checks in tests. `tests/Feature/Identity/CsrfTest.php` shows how to turn them back on.
- Factory users go through the same setup as registration (preferences, personal space). `Tests\Support\Spaces` builds family spaces and memberships.
- Test-only tables live in `tests/Fixtures/Migrations`. `tests/TestCase.php` registers that folder, so never create tables inside a test, because MySQL DDL would commit the test transaction. `Tests\Fixtures\Spaces\FixtureNote` is a ready-made space-owned model.
- **Isolation matrix:** every API route with an ID parameter must be listed in `tests/Support/IsolationMatrix.php` (route, item maker, viewer/child statuses), or `IsolationMatrixTest` fails. `Tests\Support\Content` creates module rows directly.

## Workflow

Work happens on `main`; there is no remote CI yet. Implement in the small slices listed in the blueprint. For each slice, present the objective, files, DB, API, security and test plan and wait for approval, then implement only that scope, run the tests and report the actual results. Never commit, push, deploy or do anything destructive without explicit approval.

When editing docs with a script, check the result afterwards (line count, headings). A replace whose search text is empty inserts the new text between every character.

## Keeping this file current

Update the commands above when the frontend or Docker are added, and add a section per module as modules are built.
