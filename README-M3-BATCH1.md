# SPEC-001 — Milestone 3 / Batch 1 — Core QC Workflow

This is an **incremental patch** for the verified Milestone 2 / Batch 2 repository checkpoint.

Expected starting checkpoint:

```text
branch: main
commit: 9f91b65
message: feat: complete milestone 2 batch 2 work management
tests before M3: 52 passed / 189 assertions
```

Do **not** apply this to an older project copy.

## Scope implemented

This batch implements only the approved Core QC Workflow:

- Employee Submit for QC.
- PARTIAL / FINAL submission type selected automatically.
- Count-less Custom Job treated as one QC unit.
- QC scope-based queue.
- Waiting / Reviewing / Rework Resubmitted / Reviewed tabs.
- Atomic review locking.
- Mixed approval/rework, e.g. 20 submitted -> 18 approved + 2 rework.
- Negative issues, total 0..5.
- Bonus items, total 0..5.
- 0-point rework reason supported.
- Rework must be resubmitted as the complete returned count from one source review.
- One rework submission per source review.
- Assignment and parent Work Order state recalculation.
- Employee QC history.
- QC task history without employee monthly performance totals.
- Current Client Sheet + Created With Sheet retained on QC screen.
- QC-result notifications.
- QC audit actions.
- Automated feature tests.

Not included in this batch:

- Major Error Gmail.
- Gmail send OAuth.
- Monthly performance.
- Performance Improvement Sessions.
- Monthly Team Reports.
- PDF/Excel exports.
- AI QC.
- Google Sheet parsing/sync.

## Approved state behavior

Original partial submission while original work remains:

```text
Assignment = ONGOING
QC Submission = WAITING / REVIEWING
Work Order = QC_IN_PROGRESS
```

When all original scope has been submitted:

```text
WAITING   -> Assignment SUBMITTED_QC
REVIEWING -> Assignment QC_REVIEWING
```

Mixed review example:

```text
Assigned: 40
Submitted: 20
Approved: 18
Rework: 2
Original not submitted: 20

Assignment -> REWORK
```

After the employee resubmits the returned 2:

```text
If original work remains:
Assignment -> ONGOING

If all original work was already submitted:
Assignment -> SUBMITTED_QC
```

Completion:

```text
approved_total = effective assigned total
AND unresolved rework = 0
AND active QC submissions = 0
=> Assignment COMPLETED
```

Parent Work Order:

```text
all assignments COMPLETED -> COMPLETED
any unresolved REWORK      -> REWORK
any WAITING/REVIEWING QC   -> QC_IN_PROGRESS
any started assignment     -> IN_PROGRESS
otherwise                  -> PENDING
```

## New database tables

```text
qc_submissions
qc_reviews
qc_review_issues
qc_review_bonus_items
```

Important invariants:

- `qc_submissions.idempotency_key` is unique.
- `qc_reviews.qc_submission_id` is unique.
- `qc_submissions.source_review_id` is unique when present.
- One source review can therefore produce only one rework submission.
- Approved + Rework must equal Submitted Count.
- Total negative <= 5 per review.
- Total bonus <= 5 per review.
- Approved work is never resubmitted.

## Apply patch

1. Make sure the repository is at the verified M2/B2 checkpoint.

```powershell
cd $HOME\Herd\office-work-management
git status
git log -1 --oneline
```

Expected before copying M3 files:

```text
nothing to commit, working tree clean
9f91b65 feat: complete milestone 2 batch 2 work management
```

2. Extract this ZIP **over the project root**, allowing overwrite for the listed modified files.

3. Run:

```powershell
cd $HOME\Herd\office-work-management
powershell -ExecutionPolicy Bypass -File .\setup-m3-batch1.ps1
```

The setup is non-destructive. It runs `php artisan migrate`, not `migrate:fresh`.

## Files modified from M2

```text
app/Enums/AuditAction.php
app/Models/Assignment.php
app/Http/Controllers/Employee/EmployeeWorkController.php
resources/views/employee/work/show.blade.php
resources/views/layouts/role.blade.php
routes/web.php
```

All other application files in this ZIP are new.

## Browser verification — do one flow at a time

### Check 1 — Employee PARTIAL submission

Use an assignment such as 10 assigned / 5 completed.

Expected:

```text
Submit Count max = 5
Submit 5
Submission type = PARTIAL
Assignment remains ONGOING
QC Queue receives the submission
```

### Check 2 — QC scope queue

Login with QC account that has the matching scope.

Expected:

```text
QC Queue shows only permitted Work Types.
Wrong-scope submissions cannot be opened.
```

### Check 3 — review lock

Open one Waiting submission with QC #1 and click Start Review.

Then try the same submission with QC #2.

Expected:

```text
QC #1 owns the review.
QC #2 cannot start it.
Exactly one qc_reviews row exists.
```

### Check 4 — mixed review

Example:

```text
Submitted = 20
Approved = 18
Rework = 2
Issue reason = any active negative reason
Negative points may be 0
```

Expected:

```text
Result = REWORK_REQUIRED
Assignment = REWORK
Approved Locked = 18
Unresolved Rework = 2
```

### Check 5 — rework resubmission

Employee opens the assignment.

Expected:

```text
Rework card shows QC reason/history.
Resubmit button automatically submits all 2 returned items.
The same source review cannot be resubmitted twice.
```

### Check 6 — final approval

QC reviews the 2-item rework:

```text
Approved = 2
Rework = 0
```

If the full assignment was originally submitted:

```text
Assignment = COMPLETED
Parent Work Order = COMPLETED
```

### Check 7 — points validation

Verify:

```text
Negative total > 5 -> blocked
Bonus total > 5    -> blocked
Rework with 0 negative points + a reason -> allowed
```

## Final verification before Git commit

Run:

```powershell
php artisan test tests/Feature/Qc
php artisan test
git status
```

Do not commit until all tests and browser checks pass.

Suggested commit after verification:

```powershell
git add .
git status
git commit -m "feat: add milestone 3 batch 1 core qc workflow"
git push
git status
git log -1 --oneline
```

## Important

Milestone 3 / Batch 1 is **not complete merely because this patch was extracted**.

It becomes complete only after:

1. migrations pass;
2. focused QC tests pass;
3. full regression suite passes;
4. browser verification passes;
5. Git commit/push is confirmed.
