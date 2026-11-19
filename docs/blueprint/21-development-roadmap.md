# 21. Development Roadmap (indicative; 1–2 developers with Claude Code — see Q5)

| Phase | Content | Size | Indicative |
|---|---|---|---|
| 0 Foundation | repo layout, Docker, Laravel + React skeletons, CI, conventions, ADRs, base module infrastructure | M | 1–2 wks |
| 1 LifeOS MVP | identity, spaces (personal), entitlements (free), tasks, reminders engine, notifications (in-app/email/push), calendar + important dates, notes, records + files, dashboard, search, privacy (export/delete/trash), PWA, bn/en | L | 10–14 wks |
| 1.R Beta hardening | security review, ZAP, k6, restore drill, accessibility audit, closed beta (~100 users in Dhaka) | M | 2–3 wks |
| 1.5 | phone OTP login (Q1), Google login, login alerts, device list | S | 2 wks |
| 2 ObligationOS | obligations, occurrences, receipts, due/overdue dashboard widget, SMS channel | M | 4–6 wks |
| 3 AssetOS | assets, documents, warranty reminders, maintenance logs | M | 4–5 wks |
| 4 HomeOS | homes, rooms, maintenance plans, inventory views | M | 3–4 wks |
| 5 FamilyOS | family spaces UI, invitations, roles, sharing/move, dependents, assigned tasks | L | 6–8 wks |
| 5.B Billing | payment provider integration, paid plans live | M | 3–4 wks |
| 6 Intelligence | smart reminder suggestions, cross-module workflows, AI assistant (Claude API), insights | L | TBD after data |

Note: depending on beta feedback, FamilyOS (5) may be pulled ahead of 3–4, since the family plan is the main monetization lever. The architecture allows either order.
