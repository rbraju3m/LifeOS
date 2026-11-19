# 9. Domain and Module Architecture

### 9.1 Module map

| Layer | Module | Owns (tables) | Depends on |
|---|---|---|---|
| Kernel | **Shared** | value objects (Money, LocalDateTime, RRule), base model traits, API response helpers | – |
| Platform | **Identity** | users, user_preferences, password_reset_tokens, personal_access_tokens, consents | Shared |
| Platform | **Spaces** (account/workspace + membership + authorization core) | spaces, space_members, space_invitations | Identity |
| Platform | **Entitlements** (subscriptions) | plans, plan_prices, features, plan_features, subscriptions, entitlement_overrides | Spaces |
| Platform | **Files** | files | Spaces, Entitlements |
| Platform | **Notifications** | notifications, notification_deliveries, notification_preferences, push_subscriptions | Identity |
| Platform | **Reminders** (scheduling engine) | reminders, reminder_occurrences | Spaces, Notifications, Shared (RRule) |
| Platform | **Audit** | audit_logs | Identity |
| Platform | **Tags** | tags | Spaces |
| Platform | **Privacy** | data_exports, account deletion workflow | all modules via `ExportsUserData` / `PurgesUserData` contracts |
| Feature | **Tasks** | task_lists, tasks, task_tag | Spaces, Reminders (contract), Tags |
| Feature | **Notes** | notes, note_tag, note_attachments | Spaces, Files, Tags |
| Feature | **Calendar** | events, event_exceptions, important_dates | Spaces, Reminders |
| Feature | **Records** | records, record_fields, record_attachments, record_tag | Spaces, Files, Reminders |
| Composition | **Dashboard / Search** | none (read-only aggregation) | feature modules via `DashboardProvider`, `SearchProvider` contracts |
| Future | Obligations, Assets, Home (Phase 2–4) | see §10.9 | Spaces, Files, Reminders |
| Future | Family is **not a separate data module**: family = a `Space` of type `family` + UI/invitation flows | | Spaces |

**Rules**
1. Feature modules may depend on Platform modules, never on other Feature modules. (Tags is a Platform module because Tasks, Notes and Records all link to it.) Cross-feature links, such as an asset linked to an obligation, go through IDs plus contracts.
2. Platform modules never depend on Feature modules. The Reminders and Search engines find out about features through a **registry**. For example, `ReminderSourceRegistry::register('task', TaskReminderSource::class)` is called in the Tasks provider, which inverts the dependency.
3. A module exposes only `Contracts/` (interfaces and DTOs), `Events/` and HTTP routes. Other modules must not query its Eloquent models directly. Deptrac enforces this.
4. All cross-module side effects happen through domain events dispatched **after commit** (`ShouldDispatchAfterCommit`).

**Why "Spaces" is the account/workspace concept.** Every user gets a `personal` space, and a family is a `family` space. All content carries a `space_id`, so one authorization rule ("are you an active member of this space with a role that allows this action, and does the item's visibility let you see it?") covers personal, family and future business/household contexts. Personal data is private *by construction*: nobody else is ever a member of a personal space. This is the single most important decision for not having to rework Phase 5.
- Alternative: user-owned rows plus share grants. Rejected because every query needs OR-joins over grant tables, which makes authorization and reporting error-prone.

### 9.2 Standard module layout
```
backend/app/Modules/Tasks/
  Contracts/            # public interfaces/DTOs other modules may use
  Models/Task.php, TaskList.php
  Actions/CreateTask.php, CompleteTask.php ...
  Http/Controllers/, Requests/, Resources/
  Policies/TaskPolicy.php
  Events/TaskCompleted.php   Listeners/
  Jobs/   Reminders/TaskReminderSource.php
  Database/Migrations/  Database/Factories/
  routes/api.php
  TasksServiceProvider.php
backend/tests/Modules/Tasks/{Unit,Feature}/
```

### 9.3 Cross-cutting conventions
- **Time:** the app and DB session time zone is UTC. Instants are stored as `DATETIME(3)` in UTC (not `TIMESTAMP`, because of 2038 and the implicit conversion). Wall-clock intents keep the IANA `timezone` alongside them. All-day values are `DATE`. Details in §10.10.
- **Money:** `*_amount_minor BIGINT` plus `currency CHAR(3)`. Never floats.
- **i18n:** user-generated content is stored as entered. System labels (plan names, feature descriptions) use JSON `{ "en": "...", "bn": "..." }` columns.
- **Soft delete:** user content goes to Trash for 30 days, then a purge job hard-deletes it. Security and audit tables are append-only.
- **Encryption at the application level** (Laravel `encrypted` cast, AES-256-GCM via APP_KEY with key rotation through `APP_PREVIOUS_KEYS`) for record reference numbers, record field values, 2FA secrets and serial numbers. These fields are not searchable, by design.
