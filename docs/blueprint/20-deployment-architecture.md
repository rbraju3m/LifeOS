# 20. Deployment Architecture

**Environments:** local (Docker Compose), staging (small VPS or the same VPS under a separate user and DB — the cheaper option), production.

**Local development**
- Docker Compose with php-fpm 8.4, nginx, mysql 8.4, redis 7, mailpit, minio, clamav (optional profile).
- `make up`, `make test`. Vite dev server for the frontend proxied to the API (same origin).

**Production (stage 1, ~US$40–60/month)**
- VPS: 4 vCPU / 8 GB / 160 GB NVMe in Singapore (Hetzner, DigitalOcean, Vultr or Linode). Ubuntu 24.04 LTS, unattended security upgrades, UFW (22 restricted, 80, 443), fail2ban, SSH keys only.
- Nginx (TLS via certbot, HTTP/2, gzip/brotli, static SPA cache headers) → PHP-FPM 8.4 with OPcache and JIT off.
- MySQL 8.4 (localhost, `innodb_buffer_pool_size` about 3 GB, binlog on with ROW format and 7-day retention).
- Redis 7 (localhost, `requirepass`, AOF).
- Supervisor runs `php artisan horizon`. Queues: `reminders` (high), `notifications`, `default`, `files`, `exports` (low).
- Cron: `* * * * * php artisan schedule:run`. The scheduler writes a heartbeat key.
- Cloudflare free plan in front (DNS, DDoS, caching of static assets only; API not cached).
- Object storage: Cloudflare R2 or Wasabi (main bucket plus a backup bucket on a different provider or account).

**Environment variables (key ones):**
- `APP_KEY`, `APP_PREVIOUS_KEYS`, `APP_URL`, `SANCTUM_STATEFUL_DOMAINS`, `SESSION_DOMAIN`, `SESSION_SECURE_COOKIE=true`
- `DB_*`, `REDIS_*`, `QUEUE_CONNECTION=redis`, `CACHE_STORE=redis`, `SESSION_DRIVER=redis`
- `FILESYSTEM_PRIVATE_DISK`, `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET`, `AWS_ENDPOINT`, `AWS_USE_PATH_STYLE_ENDPOINT`
- `MAIL_MAILER`, `MAIL_FROM_*`, `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`, `VAPID_SUBJECT`
- `SENTRY_LARAVEL_DSN`, `CLAMAV_SOCKET`, `REMINDER_MISSED_GRACE_MINUTES=360`, `BACKUP_*`
- Frontend build: `VITE_API_BASE=/api/v1`, `VITE_VAPID_PUBLIC_KEY`, `VITE_SENTRY_DSN`.

**Deployment:** Deployer (PHP) with zero-downtime atomic symlink releases (keeps 5 releases).
1. Pull the tagged release.
2. `composer install --no-dev -o` and `npm ci && npm run build`. The frontend is built in CI or locally and uploaded as an artifact.
3. `php artisan migrate --force`. Migrations must be **backward-compatible (expand/contract)** so a rollback never needs a down-migration.
4. `config:cache route:cache event:cache view:cache`.
5. Symlink switch.
6. `php-fpm reload`.
7. `horizon:terminate` (Supervisor restarts it).
8. Smoke test `/health/ready`.

**Rollback:** `dep rollback` (switches the symlink back to the previous release). Destructive schema changes ship only in a later "contract" release after one stable release.

**Logging and monitoring**
- Logging: Monolog JSON to `storage/logs` with daily rotation (logrotate 14 days), including request_id and user_id (ULID only, no email).
- Error monitoring: Sentry (SaaS free/team tier) with PII scrubbing, or self-hosted GlitchTip if data residency requires it.
- Uptime: UptimeRobot or Better Stack hitting `/up`, with a heartbeat URL pinged by the scheduler every 5 minutes.
- Host metrics: Netdata or provider monitoring.

**Backups and restore**
- MySQL:
  - Nightly logical backup (`mysqldump --single-transaction` → gzip → age-encrypted → off-site bucket; 30 dailies plus 12 monthlies).
  - Binlogs shipped every 15 minutes for point-in-time recovery (RPO 15 min).
  - Move to Percona XtraBackup when the DB is larger than about 20 GB.
- Redis: no backup required (rebuildable).
- Files: bucket versioning plus nightly rclone replica.
- Secrets: `.env` and `APP_KEY` stored in a password manager and as an offline encrypted copy.
- **Restore procedure** (documented runbook):
  1. Provision a VPS from the Ansible/bash bootstrap script.
  2. Restore the latest dump.
  3. Replay binlogs to the target time.
  4. Point storage at the bucket.
  5. Restore `.env`.
  6. Deploy the release tag.
  7. Run smoke tests.
  - **Drill monthly** on staging; record the time taken.
- Health checks: `/up` (liveness) and `/health/ready` (readiness); `php artisan horizon:status` alert.

**Scale path (only when metrics require it):**
1. Move MySQL to a managed DB.
2. Add a second app VPS behind a load balancer (stateless already).
3. Put workers on a separate VPS.
4. Add read replicas.
No Kubernetes until there is a demonstrated need.
