# 3. Product Scope and MVP Definition

| In MVP (Phase 1) | Designed for, not built | Explicitly excluded |
|---|---|---|
| Email auth, 2FA (TOTP), sessions | Phone OTP, Google login (Phase 1.5) | Banking/payment integrations |
| Personal space (auto-created) | Family spaces UI, invitations (Phase 5); tables exist from day 1 | Medical records |
| Tasks, task lists, recurring tasks | Subtasks, task comments | Automatic bill payments |
| Reminders (one-time, recurring, relative-to-item) | SMS channel, WhatsApp | AI assistant (Phase 6) |
| Calendar events (recurring), important dates | External calendar sync (Google/ICS) | Public sharing links |
| Notes (plain text/markdown), tags | Rich-text collaborative editing | Password manager features |
| Personal records + file attachments | OCR of documents | |
| Notifications: in-app, email, web push | Digest emails | |
| Today dashboard, global search (basic) | Analytics/insights | |
| Trash/restore, data export, account deletion | Paid subscriptions (plans exist, all users on Free) | |
| bn + en UI, PWA | Additional languages | |

**MVP acceptance definition:** every in-scope feature has API + UI + automated tests. Isolation tests pass for every endpoint. Reminders survive worker restarts without loss or duplicates. A restore drill has been done once.
