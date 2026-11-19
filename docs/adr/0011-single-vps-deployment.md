# ADR 0011: Single Linux VPS deployment with zero-downtime releases

- Status: Accepted (2026-11-18)
- Blueprint: [§20](../blueprint/20-deployment-architecture.md)

## Decision
- Production runs on one Ubuntu 24.04 VPS (4 vCPU / 8 GB, Singapore).
- Stack: Nginx + PHP-FPM, MySQL, Redis, Horizon under Supervisor, cron for the scheduler, Cloudflare in front.
- Deployer does zero-downtime releases by switching a symlink.
- Migrations follow the expand/contract pattern, so a rollback only switches the symlink.
- Docker Compose is for local development only.

## Alternatives
- **Kubernetes / containers in production:** no demonstrated need at this stage.
- **PaaS:** higher cost, and less control over data location.

## Consequences
- Running cost is roughly US$40–60/month.
- The server is a single point of failure, mitigated by tested backups, a restore runbook and a monthly restore drill.

## Migration risk
Low. The app servers are stateless, so the scale path is: managed DB → second app server → separate worker server.
