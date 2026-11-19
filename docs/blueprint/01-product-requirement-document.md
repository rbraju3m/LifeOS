# 1. Product Requirement Document (PRD)

**Problem.** People in Bangladesh track their lives across paper files, WhatsApp messages to themselves, phone alarms, Google Keep and memory. That includes bills, NID/passport renewals, birthdays, family chores and warranty cards. Things get missed: expired documents, late utility bills, forgotten anniversaries. Families have no shared, private-by-default place for this.

**Product.** One account and a "Today" dashboard that shows what needs attention: tasks, reminders, events, important dates and expiring records. Over time it extends to bills (ObligationOS), possessions (AssetOS), the household (HomeOS) and shared family life (FamilyOS).

**Goals (MVP).**
- G1 Activation: a new user creates a first task or reminder within 3 minutes of signing up.
- G2 Reliability: 99.5% of reminders are delivered within 2 minutes of their scheduled time, and none are delivered twice.
- G3 Trust: zero cross-account data exposure, enforced by automated isolation tests.
- G4 Retention: D30 retention of at least 20% in the closed beta (hypothesis to validate).

**Non-goals (MVP).** Payments or banking integration, medical records, AI features, native apps, family sharing (the architecture is ready for it, but the feature is not released), multi-currency billing, public sharing links.

**MVP feature summary.** Auth (register, verify email, login, logout, forgot password, optional TOTP 2FA), profile and preferences (language bn/en, time zone, date format), Today dashboard, tasks with lists and recurrence, reminders (standalone and attached), calendar events with recurrence, important dates (birthdays/anniversaries), notes, personal records with secure file attachments, in-app/email/web-push notifications, notification preferences, trash with restore, data export, account deletion, PWA install.

**Success metrics.** Weekly active users, the activation event, number of active reminders per user, reminder delivery latency at p95, D7/D30 retention, support tickets per 1k users.
