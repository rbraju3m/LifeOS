# 14. Family and Permission Model

**Roles** (fixed set, matrix in code at `Spaces/Authorization/RoleMatrix.php`, versioned with tests):
- Why fixed: easy to understand, test and explain to users.
- Alternative: custom roles/permission tables (flexible but complex; adds attack surface).
- Migration risk: low. The matrix is already an ability map, and moving it to DB tables later only changes where it is stored.

| Ability | owner | admin | member | child | viewer |
|---|---|---|---|---|---|
| content.view (space-visible items) | ✓ | ✓ | ✓ | ✓ | ✓ |
| content.create | ✓ | ✓ | ✓ | ✓ (tasks, notes only) | – |
| content.edit_own / delete_own | ✓ | ✓ | ✓ | ✓ | – |
| content.edit_any | ✓ | ✓ | ✓ (tasks, lists, events only) | – | – |
| content.delete_any | ✓ | ✓ | – | – | – |
| task.complete_assigned | ✓ | ✓ | ✓ | ✓ | – |
| records.view (space-visible) | ✓ | ✓ | ✓ | – | ✓ |
| records.reveal_secret | ✓ (own + space) | ✓ | own only | – | – |
| files.download | ✓ | ✓ | ✓ | items they can view | ✓ |
| members.invite | ✓ | ✓ | – | – | – |
| members.change_role / remove | ✓ | ✓ (not owner/admin) | – | – | – |
| space.settings | ✓ | ✓ | – | – | – |
| space.delete / transfer / billing | ✓ | – | – | – | – |
| audit.view_space | ✓ | ✓ | – | – | – |

**Rules**
- **Private by default for personal content:** nothing in a personal space is ever visible to anyone else. Sharing means the user explicitly *moves* an item into a family space, after a confirmation that says who will see it.
- **Within a family space**, the creator can mark an item `private` (visible only to them). Owners and admins **cannot** see other members' private items, and this is tested.
- **Child role** (Phase 5): accounts with a guardian. They have no access to records and cannot invite anyone. Age policy depends on Q6.
- **Leaving or removal:** the member's `private` items in that space are moved back to their personal space (so their data is kept). Their `space` items remain with the family. Assignments to the member are cleared. Their reminders on family items are cancelled.
- **Owner deletion:** blocked until ownership is transferred or the space is deleted.
- **Invitations:**
  - Single-use hashed token, valid 7 days.
  - Bound to an email or phone, and the accepter must be verified with that identifier.
  - The preview reveals as little as possible.
  - Rate-limited.
  - Audited.
- **Notifications about shared items** go only to members who can view the item. Recipient filtering reuses `TaskPolicy::view`.
