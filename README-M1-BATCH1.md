# Milestone 1 - Foundation / Batch 1

Implements:
- four base roles
- teams
- user lifecycle fields
- Gmail allowlist persistence
- employee capabilities
- QC scopes
- team membership history
- per-user ALLOW/DENY permission override persistence
- PostgreSQL constraints/indexes
- automated tests

## Important technical correction
SPEC-001 showed a draft `users.role` field while also selecting `spatie/laravel-permission`.
Spatie v8 warns that a User model using `HasRoles` must not have a `role` / `roles`
field or relation because it conflicts with the package.

This batch keeps the approved business behavior but stores base roles in Spatie's
roles/model_has_roles tables. The allowlist still stores `preselected_role` before registration.

## Apply
1. Commit the clean Laravel scaffold first.
2. Extract this ZIP into the Laravel project root and allow overwrite for the included files.
3. Run:

```powershell
powershell -ExecutionPolicy Bypass -File .\setup-m1-batch1.ps1
```

Expected:
- migrations complete
- roles/permissions seed
- automated tests pass

Next batch:
- Google-only Socialite login
- exact Gmail allowlist match
- registration lock
- role/team/QC scope assignment
- 24-hour absolute session
- inactive/left-company session revocation
- first Super Admin bootstrap
