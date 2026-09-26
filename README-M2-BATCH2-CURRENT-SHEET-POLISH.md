# Milestone 2 / Batch 2 — Current Client Sheet polish

Approved behavior:

- `Current Client Sheet` always points to the Client's latest active Google Sheet URL.
- `Created With Sheet` preserves the Work Order creation-time snapshot.
- Employees and Team Leaders see both links.
- When the URLs differ, the UI clearly tells users to use the current Sheet for active work.
- Historical snapshot data is not changed or deleted.
- No database migration is included.

## Install

Extract/overwrite into:

`C:\Users\Ahsan-Home\Herd\office-work-management`

Then run:

```powershell
cd $HOME\Herd\office-work-management
powershell -ExecutionPolicy Bypass -File .\setup-m2-batch2-current-sheet-polish.ps1
```

## Browser verification

Open an older Work Order whose Client Sheet URL has already changed.

Team Leader Work Order page must show:

1. Current Client Sheet — latest Client URL
2. Created With Sheet — old creation snapshot
3. Warning that the Client Sheet changed

Employee Assignment page must show the same two-link behavior.

After verification:

```powershell
git add .
git commit -m "fix: show current client sheet alongside work snapshot"
git push
```

Do not start Milestone 3 until this verification passes.
