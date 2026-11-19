# 5. Main User Journeys

- **J1 Onboarding:** landing → sign up (name, email, password, language) → verify email (the app is usable before verification, but notifications by email are held until verified) → pick time zone (auto-detected) → optional "add your first 3 important things" (one birthday, one document expiry, one task) → Today dashboard.
- **J2 Capture a task with a reminder:** Quick-add "Pay DESCO bill on 10th" → set due date → "Remind me 1 day before at 9 AM" → the reminder fires through push and email → mark done from the notification → the next recurring instance is created.
- **J3 Important date:** add "Amma's birthday, 14 March (year unknown)" → yearly reminder 3 days before and on the day.
- **J4 Store a document record:** add Passport → number, issue date, expiry → attach a photo scan → automatic expiry reminders at 6 months, 1 month and 1 week before (configurable).
- **J5 Daily check:** open the PWA → Today shows overdue items, today's tasks and events, the next 7 days of reminders and dates, and records expiring in the next 60 days.
- **J6 Data export:** Settings → Export my data → background job → email with a link → ZIP (JSON + files) downloadable for 7 days.
- **J7 Account deletion:** Settings → Delete account → re-enter password → 14-day grace period (log in to cancel) → hard purge.
- **J8 (Phase 5) Family:** create a family space → invite by email/phone → the invitee accepts → shared task list "Groceries" → assign a task to a member → that member is notified. Their personal space stays invisible to everyone else.
