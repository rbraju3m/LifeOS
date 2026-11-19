# 6. Functional Requirements (Phase 1)

**FR-ID Identity**
- ID-1 Register with name, email, password, and locale (bn/en). Passwords must be at least 10 characters and are checked against breached-password lists.
- ID-2 Email verification by signed link, valid 60 minutes, with resend.
- ID-3 Log in and log out. "Remember me" keeps the session for 30 days; otherwise it lasts 2 hours idle.
- ID-4 Forgot/reset password with a single-use token valid 60 minutes. A reset invalidates all other sessions.
- ID-5 Optional TOTP 2FA with 10 recovery codes.
- ID-6 Change email (re-verification) and change password (current password required).
- ID-7 Log out all other sessions.

**FR-PR Profile & preferences:** name, optional phone, avatar (optional), locale, time zone (IANA), date format, week start, default reminder time, quiet hours.

**FR-SP Spaces:** a personal space is created automatically and cannot be deleted while the user exists. Every content item belongs to exactly one space.

**FR-TK Tasks**
- Create, read, update and delete tasks and task lists.
- Fields: title, notes, due date, optional time, priority, list, tags.
- Statuses: open, completed, cancelled.
- Recurring tasks use RRULE; completing one instance creates the next.
- Filters: today, overdue, upcoming, by list or tag, completed.
- Soft delete with restore.

**FR-RM Reminders**
- Standalone reminders, or reminders attached to a task, event, important date or record.
- One-time or recurring.
- Relative offsets ("1 day before"), with multiple reminders per item.
- Snooze (10 min, 1 h, tomorrow) and dismiss.
- History of fired reminders.

**FR-CA Calendar:** events with start/end or all-day, location, recurrence, and per-occurrence cancel/edit. Month, week and agenda views combine events, task due dates and important dates.

**FR-ID2 Important dates:** birthday, anniversary or custom, recurring yearly. The year is optional; when it is known the UI shows age or years. Defaults to reminders on the day and N days before.

**FR-NT Notes:** title plus body (markdown subset), pin, archive, tags, attachments (files), and full-text search.

**FR-RC Records**
- Typed records (Q2) with reference number, issuer, issue/expiry dates, notes, custom label/value fields and attachments.
- Expiry reminders are auto-proposed.
- Sensitive values are masked in lists, and revealing them is audited.

**FR-FL Files**
- Upload JPG, PNG, WEBP, HEIC and PDF, up to 10 MB each on Free.
- Download through short-lived signed URLs.
- Per-space storage quota (Free: 500 MB, configurable).

**FR-NO Notifications:** in-app inbox (unread count, mark read), email, and web push. Preferences cover category × channel plus quiet hours, which delay non-urgent channels.

**FR-DB Dashboard:** the "Today" aggregation (J5) with quick-add and empty states that teach the feature.

**FR-SE Search:** search across tasks, notes, events, records and dates in the current user's accessible spaces. Uses MySQL FULLTEXT and LIKE fallbacks.

**FR-PV Privacy:** data export (J6), account deletion (J7), trash (30-day retention), and a security activity log visible to the user.
