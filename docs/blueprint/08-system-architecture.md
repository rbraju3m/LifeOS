# 8. System Architecture

```
 Browser / PWA (React+TS, Service Worker, Web Push)
        │ HTTPS (TLS 1.3)  app.lifeos.example  ← same registrable domain
        ▼
 Nginx ─── static SPA assets (immutable, hashed)
   │  /api/*, /sanctum/*, /broadcasting/*
   ▼
 PHP-FPM: Laravel modular monolith (API only, /api/v1)
   │            │                 │
   ▼            ▼                 ▼
 MySQL 8.4   Redis 7/Valkey   S3-compatible private bucket
 (primary    (sessions, cache,  (files, exports, DB backups
  data)       queues, rate lim)  in separate bucket)
                 ▲
   Horizon queue workers ── Mail (SES/Postmark), Web Push (VAPID), [SMS later]
   Scheduler (cron → schedule:run every minute)
```

### Key decisions (choice → why → alternatives → trade-offs → migration risk)

**D1 Modular monolith on Laravel 13.x / PHP 8.4** (latest stable release at project start).
- Why: one deployable and one DB transaction boundary. Laravel already ships auth, queues, scheduling, notifications and policies, it fits a small team, and PHP hosting is cheap in Bangladesh.
- Alternatives: microservices (too much operations overhead, no demonstrated need), NestJS/Node (one language with the frontend, but less built in), Django.
- Trade-off: everything shares one runtime, so we need discipline at the module boundaries (handled by D3).
- Migration risk: low. A module with clean contracts can be extracted later if a real scaling need appears.

**D2 SPA (React + TS + Vite) talking to a versioned REST API, not Inertia/Blade.**
- Why: the same API will serve native apps and partners later, and PWA/offline caching is easier.
- Alternatives: Inertia.js (faster to build, but couples the UI to server routes and weakens the case for an API-first design), Next.js (adds a Node server to run).
- Trade-off: we need to maintain an API contract and deal with CORS/cookie setup. Mitigation: SPA and API share one origin, and the OpenAPI spec generates TS types.
- Migration risk: low.

**D3 Module structure: plain PSR-4 modules under `backend/app/Modules/<Module>` with a ServiceProvider each. No third-party module package.**
- Why: no dependency lock-in, and Laravel auto-discovery isn't needed because providers are registered in `bootstrap/providers.php`. Boundaries are enforced with **Deptrac** rules in CI.
- Alternatives: `nwidart/laravel-modules` (adds generators but is opinionated and upgrade-sensitive), separate Composer packages (too heavy).
- Trade-off: more manual wiring.
- Migration risk: low.

**D4 MySQL 8.4 LTS, InnoDB, utf8mb4.**
- Why: requested, well known, and has what we need: `SKIP LOCKED`, generated columns, JSON, CHECK constraints, FULLTEXT.
- Alternative: PostgreSQL (better JSON support, partial indexes, RLS).
- Trade-off: no row-level security and no partial indexes, so we use generated columns instead.
- Migration risk: medium if Postgres is chosen later. To reduce it, avoid MySQL-only SQL outside repositories.

**D5 Primary keys: ULID `CHAR(26)` (ascii_bin) on all domain tables.**
- Why: non-enumerable public IDs (no IDOR guessing), time-ordered so B-tree inserts stay efficient, and IDs can be generated client-side for future offline/idempotent creates. Laravel `HasUlids` supports this natively.
- Alternatives: BIGINT PK plus a separate public UUID (two IDs to manage), UUIDv4 (random inserts fragment the index), BIGINT only (enumerable).
- Trade-off: 26-byte keys make indexes larger than 8-byte keys. That is acceptable below about 100M rows per table.
- Migration risk: low.

**D6 Redis (or Valkey, its license-friendly fork) for cache, sessions, queues and rate limiting. Laravel Horizon manages queues.**
- Alternative: the database queue (simpler, but slower and puts load on MySQL).
- Trade-off: Redis is another service to run, but the VPS hosts it at no extra cost.
- Persistence: AOF `everysec`. Queue jobs are rebuildable because reminder state lives in MySQL (see §15), so losing Redis loses no reminders.

**D7 Auth: Laravel Sanctum stateful SPA cookie auth plus Laravel Fortify (headless) for the auth flows.** Bearer tokens are reserved for future native apps. See §13.

**D8 Object storage: S3-compatible private bucket (Cloudflare R2, Wasabi or DigitalOcean Spaces, Singapore or nearby).**
- Why: cheap, durable, keeps files off the VPS disk. R2 has no egress fees.
- Alternative: local disk (backup and scaling problems).
- Migration risk: low thanks to the Flysystem abstraction.

**D9 OpenAPI generated from code with `dedoc/scramble`, reviewed and committed under `docs/openapi.yaml`.** The frontend types are generated with `openapi-typescript`.
- Alternative: spec-first by hand (drifts from the code).
- Trade-off: some annotations are needed for accuracy.

**D10 Frontend stack:**
- React 19, TypeScript strict, Vite, React Router, TanStack Query (server state).
- react-hook-form + zod (forms), i18next (bn/en), Tailwind CSS + Radix UI primitives (accessible) with design tokens as CSS variables, vite-plugin-pwa (Workbox).
- Testing with Vitest + Testing Library + Playwright.
- No Redux; server state lives in TanStack Query.

**D11 Deployment: one Linux VPS (Ubuntu 24.04 LTS, 4 vCPU / 8 GB), Nginx + PHP-FPM, Supervisor for Horizon, cron for the scheduler, zero-downtime releases via Deployer.** Not Kubernetes or containers in production at this stage. Docker Compose is used for local development only. See §20.

### Request flow
1. Nginx → `index.php` → middleware: TrustProxies, request-ID, `EnsureFrontendRequestsAreStateful`, auth:sanctum, throttle, `SetLocale` (user locale or `Accept-Language`), `EnsureEmailVerified` (for selected routes).
2. Route model binding is **scoped to the space**, so `/spaces/{space}/tasks/{task}` only resolves a task belonging to `{space}`.
3. FormRequest validates the input, then the Controller → Policy check → an Action class does the domain work inside a transaction → events are dispatched after commit.
4. An API Resource builds the JSON.
5. Listeners and queued jobs handle side effects: reminders, notifications, audit.
