# ADR 0006: Redis (or Valkey) for cache, sessions, queues and rate limiting; Horizon for workers

- Status: Accepted (2026-11-18)
- Blueprint: [§8](../blueprint/08-system-architecture.md), [§15](../blueprint/15-reminder-and-notification-architecture.md), [§20](../blueprint/20-deployment-architecture.md)

## Decision
- Redis 7 (or its fork Valkey, which has a more permissive license) backs the cache, sessions, queues and rate limiting.
- Laravel Horizon supervises the queue workers.
- Redis is not a source of truth. Reminder schedules live in MySQL, so losing Redis loses no reminders.

## Alternatives
**Database queue driver:** simpler, but slower and adds load on MySQL.

## Consequences
One more service to run, hosted on the same VPS at no extra cost. Redis uses AOF persistence with `everysec`.

## Migration risk
Low. Managed Redis or a switch to Valkey is a configuration change.
