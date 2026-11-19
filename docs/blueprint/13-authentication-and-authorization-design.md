# 13. Authentication and Authorization Design

**Authentication**
- **Sanctum SPA (stateful cookie)**, with the SPA and API on the same site (`app.lifeos.example` serving `/` plus `/api`).
  - Why: no tokens in JS-accessible storage, so XSS cannot steal a long-lived token. CSRF is handled with the XSRF cookie and header. Laravel supports this natively.
  - Alternatives: JWT in localStorage (vulnerable to XSS theft, revocation is hard); OAuth2 server/Passport (unnecessary for a first-party app).
  - Migration risk: native apps will use Sanctum personal access tokens (hashed, scoped abilities, expiry 90 days, revocable). The same guards and policies apply.
- Session cookie settings: `HttpOnly`, `Secure`, `SameSite=Lax`, domain-scoped. The session ID is regenerated on login and privilege change. Idle timeout 120 min; "remember me" lasts 30 days.
- **Fortify** in headless mode provides registration, login, password reset, email verification, 2FA (TOTP, RFC 6238, ±1 step window, reuse prevented) and password confirmation.
- **Password hashing:** `argon2id` (memory 64 MB, time 4, threads 1; tune so it takes about 250 ms on the VPS). Hashes are rehashed on login if the parameters change. Passwords are checked with `Password::uncompromised()` (HIBP k-anonymity: only a 5-char SHA-1 prefix leaves the server; this is disclosed in the privacy policy).
- **Brute-force protection:**
  - Throttle per IP + email.
  - Progressive lockout after 10 failures in an hour (15-minute lock, plus an email notification to the account owner).
  - Generic error messages.
- **Email verification:** the user can use the app before verifying, but email notifications, exports and family invitations require verification. Unverified accounts are purged after 30 days.
- **Step-up auth:** sensitive operations (reveal record secret, export, delete account, 2FA changes, ownership transfer) require password confirmation within the last 15 minutes.
- **Session listing:** the MVP offers "log out other devices" (Laravel `logoutOtherDevices`). A full device list (`user_sessions` table) is deferred.

**Authorization (enforced only in the backend)**
1. **Route binding scope:** `Route::scopeBindings()` makes child models resolve only inside `{space}`. Anything else → 404.
2. **`SpaceAccess` gate** (Spaces module contract): it resolves the caller's active `space_members` row once per request (memoised) → 404 if there is none.
3. **Policies per model** (`TaskPolicy`, `NotePolicy`, …) extend `SpaceContentPolicy`:
   - `view`: role has `content.view` AND (`visibility='space'` OR `created_by = me`)
   - `update`: `view` AND (role has `content.edit_any` OR (role has `content.edit_own` AND `created_by = me`) OR (task AND `assignee = me` AND only status fields change))
   - `delete`: `view` AND (`content.delete_any` OR (`content.delete_own` AND `created_by = me`))
   - `create`: role has `content.create` AND entitlement check
4. **Query scoping:** every list query goes through `->visibleTo($user)`. This is a scope that applies `space_id IN (member spaces) AND (visibility='space' OR created_by=me)`. A controller never calls `Model::all()` or `find()` without it, and an architecture test (Pest arch) bans `::find(` in controllers.
5. **Never trust the client:** `space_id`, `created_by`, `visibility` defaults and `recipient_user_id` are set server-side. Mass-assignment uses `$fillable` whitelists, and `Model::shouldBeStrict()` is on outside production.
6. Dashboard, agenda and search reuse the same `visibleTo` scopes. There are no special-case queries.
7. **Admin/support access:** none in the MVP. Operators use read-only DB access only under a documented break-glass procedure that is audited. A future support console must use explicit, time-boxed user-granted access.
