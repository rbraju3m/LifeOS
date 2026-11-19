# ADR 0007: Sanctum stateful SPA cookie auth with Fortify (headless)

- Status: Accepted (2026-11-18)
- Blueprint: [§13](../blueprint/13-authentication-and-authorization-design.md)

## Decision
- The first-party SPA uses Sanctum stateful session cookies: `HttpOnly`, `Secure`, `SameSite=Lax`, with XSRF protection.
- Fortify, in headless mode, provides the auth flows: registration, login, password reset, email verification, TOTP 2FA and password confirmation.
- Passwords are hashed with argon2id.
- Sanctum personal access tokens are reserved for future native apps.

## Alternatives
- **JWT in localStorage:** vulnerable to theft via XSS, and revocation is hard.
- **Passport/OAuth2 server:** unnecessary for a first-party app.

## Consequences
- The SPA and API must share a site so cookies work.
- No auth token is ever readable by JavaScript.

## Migration risk
Low. Native apps can use bearer tokens against the same guards and policies.
