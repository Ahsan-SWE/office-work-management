# Milestone 2 / Batch 2
## Work Orders + Assignments + Employee Progress

This batch activates the first real work-management loop without starting QC.

### Included
- Parent Work Orders:
  - `AW-000001` Assets
  - `SA-000001` Social
  - `CJ-000001` Custom
- Independent global Assignment codes: `ASN-000001`
- Client current Google Sheet URL snapshot at Work Order creation
- Configuration snapshots so later settings changes do not alter old work
- Multiple assignments inside one Work Order
- Priority: NORMAL / HIGH / URGENT
- Assets: section, full/partial scope, site list, employee, count
- Social: target section, activities / Full Activity, employee, count
- Custom: predefined or custom job, mandatory instruction
- Normal Custom Job: one employee
- Special Custom Job: multi-employee split
- Capability mismatch warning with explicit Assign Anyway
- Workload threshold warning with explicit Assign Anyway
- Inactive Client blocks new work
- Employee My Work queue
- Pending -> Start Work -> Ongoing
- Team Lead / creator notification when work starts
- Manual progress count and progress notes
- Every progress update preserved in `assignment_progress_logs`
- Reassignment history table prepared for later workflow

### Prepared but not activated
The database already supports later statuses such as:
`SUBMITTED_QC`, `QC_REVIEWING`, `REWORK`, `COMPLETED`,
`DUPLICATE_REVIEW`, `DUPLICATE_CONFIRMED`, `CANCELLED`.

Partial QC, QC locks, QC marks, rework review, proof upload,
duplicate-completed handling, and final completion are not activated in this batch.

## Install

Extract into:

`C:\Users\Ahsan-Home\Herd\office-work-management`

Overwrite included files.

Run:

```powershell
cd $HOME\Herd\office-work-management
Test-Path .\setup-m2-batch2.ps1
```

Expected:

```text
True
```

Then:

```powershell
powershell -ExecutionPolicy Bypass -File .\setup-m2-batch2.ps1
```

This script does **not** run `migrate:fresh`.

Expected final message:

```text
M2 Batch 2 completed successfully.
Next: browser verification only. Do not start Milestone 3 yet.
```

## Browser verification order

1. Team Leader -> Assign Work -> Team Assets.
2. Select the existing active client.
3. Create an Assets Work Order with at least two independent assignments.
4. Confirm generated Work/Assignment codes and Google Sheet snapshot.
5. Login as Employee -> My Work.
6. Open one assignment and click Start Work.
7. Save at least two progress updates.
8. Return as Team Leader and confirm progress timeline.
9. Create one Social Work Order using Full Activity.
10. Create one normal Custom Job with one employee.
11. Optionally test Special Custom Job with two employees.
12. Change the Client's current Google Sheet URL and confirm existing Work Orders still show their original snapshot.
13. Deactivate the Client and confirm new Work creation is blocked.

Only after all browser checks pass should Batch 2 be committed and Milestone 3 begin.

## Commit after verification

```powershell
git add .
git commit -m "feat: milestone 2 batch 2 work orders and assignments"
git push
```
