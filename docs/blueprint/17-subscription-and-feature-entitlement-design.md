# 17. Subscription and Feature Entitlement Design

- **The data model** is in §10.8: plans, plan_prices, features, plan_features, subscriptions, entitlement_overrides.
- **Resolution:** `Entitlements::for(Space $space)->limit('storage.max_bytes')` / `->allows('reminders.channel.sms')`.
  - Precedence: active override > live subscription's plan > `free` plan.
  - The result is cached in Redis per space for 10 minutes and invalidated when a subscription or override changes.
- **Usage:** `Entitlements::assertCanCreate($space, 'records.max_count', currentCount)` is called inside Actions, never in controllers or the frontend. The frontend receives `/spaces/{space}/entitlements` only for display.
- **Initial seed values (hypotheses; changed by DB seed/admin, never by code):**

| Feature | Free | Personal Plus | Family | Family Pro |
|---|---|---|---|---|
| storage.max_bytes | 500 MB | 5 GB | 10 GB shared | 50 GB shared |
| files.max_upload_bytes | 10 MB | 25 MB | 25 MB | 50 MB |
| space.max_members | 1 (personal) | 1 | 6 | 12 |
| space.max_family_spaces (per user) | 1 (Phase 5, ≤3 members) | 1 | 1 | 3 |
| records.max_count | 50 | ∞ | ∞ | ∞ |
| reminders.max_active | 500 | ∞ | ∞ | ∞ |
| reminders.sms_per_month | 0 | 30 | 60 | 200 |
| export.enabled | ✓ (always) | ✓ | ✓ | ✓ |

- **Data is never held hostage:**
  - Downgrading or limit breaches block only *new* creation above the limit.
  - Read, edit, delete and export always work.
  - `export.enabled` is forced true in code as an invariant and covered by a test.
- **Billing providers** are not in the MVP. There is a `BillingProvider` interface with webhook handlers that update `subscriptions` (idempotent on provider event ID, stored in a future `billing_events` table).
- **Free tier guarantees** are documented publicly.
