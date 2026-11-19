# 0. Context

LifeOS is a free-first consumer platform for Bangladesh (then global) that will cover five connected domains: Life, Obligations, Assets, Home and Family. They share one account, one dashboard, one notification system and one permission model. This blueprint covers the 22 requested planning documents and gives a phase-by-phase build plan. Phase 1 (LifeOS Foundation) is the only phase specified at implementation depth. Later phases are specified enough to prove the core architecture can hold them without rework.

### 0.1 Essential open questions (each has a stated default so planning is not blocked)

| # | Question | Why it matters | Default assumed in this blueprint |
|---|---|---|---|
| Q1 | Sign-in methods for MVP: email+password only, or also phone OTP / Google? | Bangladeshi users are phone-first, but SMS OTP costs money and needs a local SMS provider | Email + password with email verification. Phone number is optional and unverified. Phone OTP and Google sign-in come in Phase 1.5, behind an `IdentityProvider` abstraction |
| Q2 | What exactly are "Personal records" in the MVP? | Without a clear definition this turns into a generic catch-all table | Structured identity and reference documents (NID, passport, driving licence, TIN, birth certificate, vehicle registration, education certificate, other). Each has a number, issuer, issue/expiry dates, notes and attached scans. No passwords, PINs, card numbers or medical data |
| Q3 | Hosting region and data residency | Bangladesh data-protection law (status of the Personal Data Protection Ordinance/Act to be confirmed with counsel) may require data to stay in Bangladesh | One VPS in Singapore (about 40–60 ms from Dhaka) plus S3-compatible storage in the same region. Revisit before public launch with legal advice |
| Q4 | Notification channels in MVP | Cost and provider choice | In-app + email + Web Push. SMS is designed in but switched off by entitlement and comes in a later phase |
| Q5 | Team size and target timeline | Needed to turn roadmap sizes into dates | 1–2 developers working with Claude Code. Estimates below are relative (S/M/L) with indicative weeks |
| Q6 | Minimum user age / child accounts | Consent law; FamilyOS child role | MVP is 18+ self-signup. Child/dependent profiles are designed for Phase 5 |
| Q7 | Native mobile apps planned? | Affects the auth token strategy | PWA first. The API is designed so native apps can later use Sanctum bearer tokens without changes |
| Q8 | Bengali/Hijri calendar support for important dates (e.g., Eid, Pohela Boishakh) | Cultural fit in Bangladesh | Gregorian only in MVP. The `calendar_system` column exists so Hijri/Bengali can be added later without migration pain |
| Q9 | Brand/trademark: "LifeOS" is already used by several products worldwide | Global expansion and app-store listing risk | Keep as working name. Run a trademark check before buying the domain and doing marketing |
