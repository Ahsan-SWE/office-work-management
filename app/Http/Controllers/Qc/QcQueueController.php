<?php

namespace App\Http\Controllers\Qc;

use App\Enums\QcSubmissionStatus;
use App\Enums\QcSubmissionType;
use App\Http\Controllers\Controller;
use App\Models\QcSubmission;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QcQueueController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('qc.review'), 403);

        $tab = in_array($request->string('tab')->toString(), [
            'waiting',
            'reviewing',
            'rework',
            'reviewed',
        ], true)
            ? $request->string('tab')->toString()
            : 'waiting';

        $scopes = $request->user()->qcScopes()
            ->whereNull('revoked_at')
            ->orderBy('scope')
            ->pluck('scope');

        $query = QcSubmission::query()
            ->select('qc_submissions.*')
            ->join('assignments', 'assignments.id', '=', 'qc_submissions.assignment_id')
            ->join('work_orders', 'work_orders.id', '=', 'assignments.work_order_id')
            ->with([
                'assignment.employee:id,name,email',
                'assignment.section:id,name',
                'assignment.workOrder.client:id,client_code,name',
                'sourceReview:id,review_code',
                'review.reviewer:id,name,email',
            ]);

        if ($scopes->isEmpty()) {
            $query->whereRaw('1 = 0');
        } else {
            $query->whereIn('work_orders.work_type', $scopes->all());
        }

        match ($tab) {
            'reviewing' => $query->where('qc_submissions.status', QcSubmissionStatus::REVIEWING->value),
            'rework' => $query
                ->where('qc_submissions.status', QcSubmissionStatus::WAITING->value)
                ->where('qc_submissions.submission_type', QcSubmissionType::REWORK->value),
            'reviewed' => $query->where('qc_submissions.status', QcSubmissionStatus::REVIEWED->value),
            default => $query
                ->where('qc_submissions.status', QcSubmissionStatus::WAITING->value)
                ->where('qc_submissions.submission_type', '!=', QcSubmissionType::REWORK->value),
        };

        $query
            ->orderByRaw("CASE qc_submissions.submission_type WHEN 'REWORK' THEN 1 ELSE 2 END")
            ->orderByRaw("CASE assignments.priority WHEN 'URGENT' THEN 1 WHEN 'HIGH' THEN 2 ELSE 3 END")
            ->orderBy('qc_submissions.submitted_at');

        return view('qc.queue', [
            'tab' => $tab,
            'scopes' => $scopes,
            'submissions' => $query->paginate(25)->withQueryString(),
        ]);
    }
}
