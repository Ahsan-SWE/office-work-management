# M3 Batch 1 — Current Sheet Label Regression Fix

This is an incremental patch only.

## Problem

Milestone 3 kept the approved Current Client Sheet link and URL behavior, but changed the employee link text from:

`Open Current Google Sheet`

to:

`Open Current Client Sheet`

The existing Milestone 2 regression test intentionally verifies the approved UI text and therefore failed.

## Fix

Only `resources/views/employee/work/show.blade.php` is changed.

The link target remains the Client's latest current Google Sheet URL.  
The historical `Created With Sheet` snapshot behavior is unchanged.  
No database migration or QC logic is changed.

## Apply

Extract this ZIP over the existing project root, then run:

```powershell
cd $HOME\Herd\office-work-management
powershell -ExecutionPolicy Bypass -File .\setup-m3-batch1-current-sheet-label-fix.ps1
```

Do not commit until the full M3 Batch 1 browser verification is complete.
