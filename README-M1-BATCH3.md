# Milestone 1 - Batch 3
## Super Admin Management UI

This batch adds:

- Super Admin dashboard shell and navigation
- dashboard summary cards + recent administration activity
- Teams list/create/edit/deactivate/reactivate
- one active team per Team Leader enforcement
- Team Leader primary-team/membership history updates
- Allowed Gmail/email list/create/edit/disable/enable
- employee Team preselection
- QC initial scope preselection
- Users list/detail/search/filter
- user lifecycle status management + session-version revocation
- employee capabilities
- QC multi-scope management with revocation history
- explicit per-user ALLOW/DENY permission overrides
- authorization integration for permission overrides
- append-only audit log foundation
- automated tests

## Important: this batch does NOT reset the database

You now have a real Super Admin account. The setup script uses:

`php artisan migrate`

not:

`php artisan migrate:fresh`

so your real login account is preserved.

## Apply

1. Keep your `php artisan serve` terminal separate.
2. In another PowerShell, commit Batch 2:

```powershell
cd $HOME\Herd\office-work-management
git add .
git commit -m "feat: milestone 1 batch 2 google authentication"
```

3. Extract this ZIP into the Laravel project root with overwrite enabled.
4. Confirm:

```powershell
Test-Path .\setup-m1-batch3.ps1
```

Expected: `True`

5. Run:

```powershell
powershell -ExecutionPolicy Bypass -File .\setup-m1-batch3.ps1
```

The script stops immediately if any migration, frontend build, seeder or test fails.

## Browser verification

Start/restart the local app if needed:

```powershell
php artisan serve --host=127.0.0.1 --port=8000
```

Open:

`http://localhost:8000/dashboard`

Your Super Admin should automatically reach the new admin dashboard.

Recommended manual test:

1. Create a Team with no Team Leader.
2. Add a Team Leader Gmail to Allowed Gmail.
3. Sign out and test Team Leader registration in a separate/private browser after adding the Google account as an OAuth Test User.
4. Sign back in as Super Admin and assign the registered Team Leader to the Team.
5. Approve one Employee Gmail and preselect the Team.
6. Register that Employee and verify role/team assignment.
7. Set Employee capabilities.
8. Approve one QC Gmail with an initial scope, then add a second temporary QC scope from Users.

## Important business rules preserved

- Super Admin cannot be created through the allowlist.
- Registered allowlist rows cannot be silently edited.
- One Team Leader leads one active team in the MVP.
- Deactivating a user increments `session_version`, invalidating existing sessions.
- A current Team Leader must be reassigned before account deactivation.
- When Assignment tables are added in Milestone 2, user deactivation automatically checks for active assignments and blocks until handover.
- Explicit per-user DENY/ALLOW takes precedence over role defaults.
- Important admin changes are written to `audit_logs`.

## Next batch

Milestone 1 - Batch 4 will finish the foundation:

- Team Leader / Employee / QC role dashboards
- role-specific navigation shells
- Team Leader own-team user management baseline
- improved admin user role lifecycle where appropriate
- audit log viewer for Super Admin
- notification shell
- security regression tests
- Milestone 1 final integration test
