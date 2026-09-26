# Milestone 1 / Batch 3 - Hotfix 1

## What failed

`PermissionOverrideTest` correctly exposed a precedence conflict.

The application needs this order:

1. explicit per-user DENY / ALLOW
2. role/direct permission from Spatie
3. normal Laravel Gate / policy behavior

Spatie's default configuration registers its own `Gate::before()` permission
checker. A role permission can therefore return `true` before the Office-specific
DENY callback gets a chance to decide.

## Fix

- Disable Spatie's default Gate permission-check registration:
  `register_permission_check_method => false`
- Register one application-owned `Gate::before()` callback.
- Check explicit Office permission override first.
- If no override exists, delegate to Spatie's `checkPermissionTo()`.
- Return `null` when neither grants access so normal Laravel policies can continue.

This preserves Spatie's roles/permissions while making SPEC-001 user overrides authoritative.

## Apply

Extract into the project root with overwrite enabled, then run:

```powershell
powershell -ExecutionPolicy Bypass -File .\setup-m1-batch3-hotfix1.ps1
```

Expected final result:

```text
PASS  Tests\Feature\Admin\PermissionOverrideTest
...
Tests: all passed
Batch 3 Hotfix 1 completed successfully.
```

No database reset is performed.
