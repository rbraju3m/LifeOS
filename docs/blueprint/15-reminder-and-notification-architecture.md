# 15. Reminder and Notification Architecture

**Components**
1. **Scheduling source of truth = MySQL** (`reminders.next_fire_at`). Redis/queues are only transport. Losing Redis loses nothing: the dispatcher re-derives due work from the database.
2. **Dispatcher** — `reminders:dispatch` runs every minute via the scheduler (`withoutOverlapping`, `onOneServer`):
   ```
   loop (batches of 500 until none or 50s elapsed):
     BEGIN;
     SELECT id FROM reminders WHERE status='active' AND next_fire_at <= NOW(3) + INTERVAL 30 SECOND
       AND deleted_at IS NULL ORDER BY next_fire_at LIMIT 500 FOR UPDATE SKIP LOCKED;
     for each: INSERT IGNORE reminder_occurrences(reminder_id, scheduled_for=next_fire_at, status='pending')
               compute next = RecurrenceCalculator::next(reminder, after=next_fire_at)  // in reminder.timezone
               UPDATE reminders SET last_fired_at=next_fire_at, next_fire_at=next|NULL, status=(next?active:completed)
     COMMIT;
     dispatch DeliverReminderOccurrence(occurrence_id) jobs (afterCommit) on queue 'reminders'
   ```
   - `SKIP LOCKED` makes it safe to run several dispatchers. The unique key on the occurrence makes double inserts impossible.
   - The 30-second look-ahead plus job `delay` until `scheduled_for` gives on-time delivery.
3. **Missed-window policy:** if `scheduled_for` is more than 6 hours in the past (after an outage), the occurrence is still created, but with status `missed` and an in-app notification only ("You had a reminder at …"). Recurring reminders never fire a backlog storm: the calculator jumps to the next future occurrence.
4. **DeliverReminderOccurrence job:**
   - Re-checks that the reminder is active, the version matches, the recipient can still view the source (policy), and the occurrence is still `pending|snoozed`.
   - Creates a `notifications` row (`dedupe_key = "rem:{occurrence_id}"`, unique) and `notification_deliveries` per channel, based on: `reminder.channels` ∩ user preferences ∩ entitlements ∩ verified channels.
   - Dispatches one channel job per delivery.
5. **Channel jobs:** `SendEmailDelivery`, `SendPushDelivery`, later `SendSmsDelivery`.
   - Each checks `delivery.status == queued` before sending (idempotent).
   - Retries: `tries=5`, backoff `[30, 120, 600, 1800, 3600]` s.
   - Permanent errors (invalid address, push 404/410) → `failed`/`skipped` with no retry.
   - Provider idempotency keys are used where supported.
   - Quiet hours: push and SMS are delayed until quiet hours end. In-app is never delayed. Email is delayed unless the category is `security`.
6. **Relative reminders** (task due −1 day at 09:00, record expiry −30 days):
   - The source module implements `ReminderSource::anchorFor(sourceId, occurrenceAfter)` and returns the local anchor date/time plus time zone.
   - When the source's date changes, it emits `ReminderAnchorChanged`, and the engine recomputes `next_fire_at` and bumps `version`.
   - Recurring tasks: when the next instance is generated, its reminders are cloned from the "reminder templates" of the previous instance.
7. **Defaults:**
   - Record expiry: auto-suggest −180, −30 and −7 days at the default reminder time.
   - Important dates: −3 days and on the day.
   - All are user-editable.
8. **Snooze** creates a new occurrence-level state (`snoozed_until`). A secondary minutely sweep re-dispatches due snoozes, keeping the unique key on `(reminder_id, scheduled_for)`: the snooze updates the same occurrence row rather than creating a new one.
9. **Time zones / DST:** the next fire time is computed in the reminder's IANA zone from the wall-clock rule, then converted to UTC.
   - A non-existent local time (spring-forward gap) shifts forward to the first valid minute.
   - An ambiguous time (fall-back) uses the first occurrence.
   - Bangladesh has no DST, but tests cover `America/New_York` and `Europe/London`.
10. **Notification history:** `reminder_occurrences` plus `notifications` and `notification_deliveries` provide a full audit trail per occurrence. Users see "Reminder history" per reminder.
11. **Channels:**
   - In-app: the DB row, plus polling `unread-count` every 60 s while the app is visible. WebSockets via Laravel Reverb are deferred because they are not needed for the MVP.
   - Email: Amazon SES (cheapest) or Postmark (best deliverability), behind Laravel Mail. SPF, DKIM and DMARC are configured.
   - Web Push: VAPID via `minishlink/web-push`. iOS requires the PWA to be installed to the home screen (shown as an onboarding hint).
   - SMS: Phase 2+, Bangladeshi provider behind an `SmsGateway` interface, entitlement-gated.
12. **Monitoring:**
   - Metric "reminder lag" = `delivered_at − scheduled_for` (p50/p95), logged per batch.
   - Alert if the scheduler heartbeat is older than 3 minutes or the `reminders` queue backlog is above 1000 or older than 5 minutes.
   - Horizon dashboard (admin-only, IP + auth gated).
