# 7. Non-functional Requirements

| Area | Requirement |
|---|---|
| Performance | API p95 < 300 ms for reads and < 500 ms for writes at 50 req/s on 1 VPS. First load on a 3G-class connection is under 3 s (JS bundle < 250 KB gzipped for the initial route) |
| Reliability | Reminder delivery: p95 < 2 min late, 0 duplicates, catch-up after an outage. 99.5% monthly availability target in the early stage |
| Security | OWASP ASVS L2 as the baseline. TLS 1.2+ only. Authorization checked on the backend for every request. Isolation tests are required to merge |
| Privacy | Data minimisation. Sensitive fields encrypted at the application level. No third-party trackers inside the app; analytics are self-hosted or privacy-preserving (Plausible/Umami) |
| Accessibility | WCAG 2.2 AA, minimum 44 px touch targets, works at 200% zoom, screen-reader labels in both languages |
| i18n/l10n | All strings externalised (bn, en). Dates, numbers and currency through the Intl API. Optional Bengali digits. IANA time zones. Currency is ISO 4217 with amounts in minor units |
| Scalability | Comfortable up to about 100k users on 1–2 servers. Stateless app servers (sessions and cache in Redis) so it can scale horizontally |
| Maintainability | Module boundaries enforced by Deptrac in CI. Static analysis with PHPStan level 8 and `tsc --strict`. Test coverage at least 80% on domain/auth code |
| Observability | Structured JSON logs with a request ID, error tracking with PII scrubbing, queue and scheduler heartbeats, uptime checks |
| Recoverability | RPO ≤ 15 min (binlog), RTO ≤ 4 h. Monthly restore drill |
| Compatibility | Last 2 versions of Chrome/Android WebView, Safari iOS 16.4+ (needed for web push), Firefox, Edge |
