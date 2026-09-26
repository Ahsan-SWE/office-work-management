# Milestone 2 / Batch 1
## Client Directory + Configurable Work Catalogs

This batch begins the core business domain.

## Included

### Client directory
- global Client directory for Super Admin + Team Leaders
- one long-running Client record reused for repeated work
- readable `CL-000001` client codes
- Active / Inactive lifecycle
- Google Sheet link is the source-of-truth reference
- no automatic Google Sheet parsing
- duplicate/similar-name warning that can be explicitly overridden
- optional start date / expected end date
- current tier
- client search/filter
- full client page shell ready for Work Orders

### Historical integrity
- Google Sheet URL history
- old sheet URLs remain available after an update
- Tier history
- Super Admin tier correction creates historical records
- Team Leaders cannot directly change an existing client's tier
- normal Tier Upgrade workflow will be completed later through Custom Job + QC

### Configurable catalogs
- Tier 1-5 defaults
- custom global tiers
- Asset Sections
- Social Activity Types
- Full Activity default membership
- Custom Job Types + default instruction
- QC Negative Reasons
- QC Bonus Reasons
- deactivate instead of hard-delete

### Authorization
- Super Admin and Team Leader can view/reuse all Clients
- Employee cannot browse Clients
- QC cannot browse Clients
- Team Leader can add a custom Tier
- only Super Admin can edit/deactivate global configuration catalogs

## Data preserved

The setup script does **not** run `migrate:fresh`.

Existing:
- Super Admin
- Team Alpha
- Team Leader
- Employee
- QC
- scopes/capabilities
- audit records
- sessions

remain intact.

## Install

Extract directly into:

`C:\Users\Ahsan-Home\Herd\office-work-management`

Overwrite included files.

Then:

```powershell
cd $HOME\Herd\office-work-management
Test-Path .\setup-m2-batch1.ps1
```

Expected: `True`

Run:

```powershell
powershell -ExecutionPolicy Bypass -File .\setup-m2-batch1.ps1
```

Expected final message:

```text
M2 Batch 1 completed successfully.
Next: verify Settings and create the first real Client.
```

## Browser verification after tests pass

### Super Admin
- sidebar now has Clients + Settings
- Settings shows default catalogs
- create/edit/deactivate configuration
- Clients can be searched globally

### Team Leader
- sidebar now has Clients
- can create a Client
- can add a custom Tier
- can update Client name/sheet/dates
- cannot directly change an existing Client tier

### Suggested first Client
Name:
`Sam Andasi CC Doc`

Google Sheet:
use a real test Google Sheet URL

Tier:
`Tier 1`

Then edit its Google Sheet URL once and verify that the Client page shows both the old and current URL.

## Commit after browser verification

```powershell
git add .
git commit -m "feat: milestone 2 batch 1 client directory and configuration"
git push
```

## Next batch

Milestone 2 / Batch 2:
- Work Orders
- Assignments
- ASSETS / SOCIAL / CUSTOM forms
- configuration snapshots
- sheet URL snapshot
- priorities
- workload/capability warnings
- assignment status foundation
