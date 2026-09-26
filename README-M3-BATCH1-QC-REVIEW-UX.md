# M3 Batch 1 — QC Review Dynamic Items + Scroll Patch

Incremental patch only. It does not redesign or restart M3.

## Changes

- QC Review starts with exactly **1 Issue** row.
- `+ Add Issue` adds another row only when needed.
- Added Issue rows can be removed.
- Issue rows are re-indexed before submission.
- Server accepts up to 50 Issue rows; total negative points remains capped at 5.
- QC Review starts with exactly **1 Bonus** row.
- `+ Add Bonus` adds another row only when needed.
- Added Bonus rows can be removed.
- Bonus rows are capped at 5 because each saved bonus item is at least 1 point and the approved total bonus cap is 5.
- Validation errors preserve the rows and values entered by QC.
- On desktop, the entire right review column is sticky and gets its **own vertical scrollbar** when it becomes taller than the viewport.
- On mobile/small screens, normal page scrolling remains in place.

## Apply

Extract this ZIP over:

`C:\Users\Ahsan-Home\Herd\office-work-management`

Then run:

```powershell
cd $HOME\Herd\office-work-management
powershell -ExecutionPolicy Bypass -File .\setup-m3-batch1-qc-review-ux.ps1
```

Do not commit until browser verification is complete.
