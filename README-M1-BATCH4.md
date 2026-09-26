# Milestone 1 - Batch 4
## Role Dashboards, Team Leader Boundary, Audit Viewer, Notification Shell

This batch completes the Milestone 1 application shell.

## Included

- role-aware `/dashboard` redirect
- Team Leader dashboard
- Employee dashboard
- QC dashboard
- role-specific sidebar navigation
- Team Leader own-team employee list
- Team Leader employee status/capability management
- hard team-boundary enforcement for Team Leaders
- Super Admin audit log viewer
- notification table/model/controller/UI shell
- unread + Attention Needed notification filters
- notification ownership security
- additional authorization regression tests
- full test-suite execution with fail-fast setup script

## No data reset

The setup script uses:

`php artisan migrate`

Your existing:
- Super Admin
- Team Alpha
- registered Team Leader
- registered Employee
- registered QC
- capabilities
- QC scopes

remain intact.

## Apply

First commit/push Batch 3 if not already done.

Extract this ZIP directly into:

`C:\Users\Ahsan-Home\Herd\office-work-management`

Overwrite included files.

Then:

```powershell
cd $HOME\Herd\office-work-management
Test-Path .\setup-m1-batch4.ps1
```

Expected:

`True`

Run:

```powershell
powershell -ExecutionPolicy Bypass -File .\setup-m1-batch4.ps1
```

## Browser verification after tests pass

### Team Leader

Open a private browser and sign in with the registered Team Leader.

Expected:
- Team Leader Dashboard
- Team Alpha displayed
- My Team
- registered Employee visible
- Team Leader can edit that Employee's capabilities/status
- Team Leader cannot access Super Admin pages

### Employee

Sign in as Employee.

Expected:
- Employee Dashboard
- Team Alpha
- ASSETS / SOCIAL / CUSTOM capability display
- role-specific navigation shell

### QC

Sign in as QC.

Expected:
- QC Dashboard
- three active scopes displayed
- Waiting for QC / Rework / Reviewed navigation shells

### Super Admin

Expected new sidebar:
- Audit Logs
- Notifications

Audit Logs should show the changes already performed during Batch 3 testing.

## Important limitation

Work, QC queue counts, performance and report data are intentionally placeholders until
their actual domain tables are implemented in Milestones 2-5. The authorization and navigation
shells are real; fake business records are not generated.

## After verification

Commit and push:

```powershell
git add .
git commit -m "feat: complete milestone 1 foundation"
git push
```

Then start Milestone 2:
Clients + configuration + Work Orders + Assignments.
