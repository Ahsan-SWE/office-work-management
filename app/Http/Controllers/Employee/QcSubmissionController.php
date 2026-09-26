<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\QcReview;
use App\Services\Qc\QcSubmissionService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class QcSubmissionController extends Controller
{
    public function store(
        Request $request,
        Assignment $assignment,
        QcSubmissionService $service,
    ): RedirectResponse {
        $this->owner($request, $assignment);
        abort_unless($request->user()->can('task.submit_qc'), 403);

        $data = $request->validate([
            'idempotency_key' => ['required', 'uuid'],
            'submitted_count' => ['required', 'integer', 'min:1', 'max:1000000'],
            'scope_text' => ['nullable', 'string', 'max:10000'],
            'employee_note' => ['nullable', 'string', 'max:10000'],
        ]);

        try {
            $submission = $service->submitOriginal(
                $assignment,
                $request->user(),
                (int) $data['submitted_count'],
                $data['idempotency_key'],
                $data['scope_text'] ?? null,
                $data['employee_note'] ?? null,
            );
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['qc' => $exception->getMessage()]);
        }

        return back()->with(
            'success',
            "{$submission->submission_code} submitted for QC ({$submission->submission_type->value})."
        );
    }

    public function storeRework(
        Request $request,
        Assignment $assignment,
        QcReview $review,
        QcSubmissionService $service,
    ): RedirectResponse {
        $this->owner($request, $assignment);
        abort_unless($request->user()->can('task.submit_qc'), 403);

        $data = $request->validate([
            'idempotency_key' => ['required', 'uuid'],
            'employee_note' => ['nullable', 'string', 'max:10000'],
        ]);

        try {
            $submission = $service->submitRework(
                $assignment,
                $review,
                $request->user(),
                $data['idempotency_key'],
                $data['employee_note'] ?? null,
            );
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['qc' => $exception->getMessage()]);
        }

        return back()->with(
            'success',
            "{$submission->submission_code} rework resubmitted for QC."
        );
    }

    private function owner(Request $request, Assignment $assignment): void
    {
        abort_unless($assignment->employee_id === $request->user()->id, 403);
    }
}
