# 18. Security Threat Model (STRIDE, MVP scope)

| # | Threat | Asset / entry point | Mitigation | Test |
|---|---|---|---|---|
| S1 | Credential stuffing / brute force | /auth/login | throttling, lockout, breached-password check, optional 2FA, login notification email for new device (Phase 1.5) | rate-limit feature tests |
| S2 | Session hijack | cookies | HttpOnly/Secure/SameSite, HSTS preload, session rotation, no tokens in JS | header tests |
| T1 | CSRF | state-changing endpoints | Sanctum XSRF + SameSite=Lax + Origin check (stateful domains) | CSRF tests |
| T2 | Mass assignment / tampering with space_id, created_by, role | all writes | `$fillable` whitelist, server-set ownership, FormRequest validation | feature tests |
| R1 | Repudiation of sensitive actions | reveal, export, membership | audit_logs with actor, IP, UA | audit tests |
| I1 | **IDOR / cross-account read** | every `{id}` | scoped bindings, policies, `visibleTo` scope, ULIDs, 404 semantics | **isolation matrix (mandatory)** |
| I2 | Family member sees private item | family spaces | visibility=private enforced in policy + scopes; admins excluded | permission tests |
| I3 | File URL leakage | downloads | private bucket, 5-min presigned URLs, no predictable keys, `Referrer-Policy: no-referrer` | file access tests |
| I4 | DB/backup breach | data at rest | disk encryption (provider), app-level encryption of sensitive fields, encrypted off-site backups, least-privilege DB users | key rotation test |
| I5 | Sensitive data in logs/error tracking | logs, Sentry | log scrubbing processor, `$hidden` attributes, Sentry `before_send` PII filter, no request bodies logged | unit tests on scrubber |
| I6 | User enumeration | register/forgot | generic responses and equal timing | tests |
| I7 | XSS → data theft | notes (markdown), names | React escaping, markdown rendered with a sanitizer (DOMPurify) and no raw HTML, strict CSP (`default-src 'self'`, no inline script) | CSP header test, XSS fixtures |
| D1 | Abuse / DoS by heavy requests | search, uploads, agenda expansion | rate limits, window caps (92 d), COUNT caps on RRULE, upload size caps, Nginx limits; Cloudflare free tier in front | limit tests |
| D2 | Reminder storms | recurrence | min interval daily for user rules, max active reminders, catch-up policy | engine tests |
| E1 | Privilege escalation in family | member role changes | role matrix, owner-only operations, cannot invite as owner, cannot change own role | permission tests |
| E2 | Malicious upload | files | MIME sniffing + allow-list, ClamAV, image re-encode, attachment disposition, separate download domain not needed since files come from the S3 domain | upload tests |
| E3 | Invitation token theft | invitations | hashed tokens, single use, 7-day TTL, bound to identifier | invitation tests |
| X1 | Supply chain | composer/npm | lock files, `composer audit`/`npm audit` in CI, Dependabot/Renovate when a remote exists, minimal deps | CI |
| X2 | Secret leakage | .env, keys | secrets only in server env, never committed; APP_KEY backed up offline (losing it = losing encrypted fields) | deploy checklist |

**Security headers:** HSTS (1 year, includeSubDomains), CSP, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin` (no-referrer on file routes), `Permissions-Policy`, `frame-ancestors 'none'`.
**Encryption in transit:** TLS via Let's Encrypt (certbot) on Nginx. TLS between the app and object storage. MySQL and Redis listen on localhost only.
