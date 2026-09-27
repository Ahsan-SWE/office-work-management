<?php

namespace App\Http\Controllers\Qc;

use App\Http\Controllers\Controller;
use App\Models\QcReview;
use App\Services\Qc\QcGmailService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class QcMajorErrorEmailController extends Controller
{
    public function store(
        Request $request,
        QcReview $review,
        QcGmailService $gmail,
    ): RedirectResponse {
        abort_unless($request->user()->can('qc.review'), 403);

        $data = $request->validate([
            'extra_cc' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $extraCc = $this->parseExtraCc($data['extra_cc'] ?? null);
            $attempt = $gmail->sendMajorErrorEmail($review, $request->user(), $extraCc);
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['gmail' => $exception->getMessage()]);
        }

        return back()->with(
            'success',
            "Major Error Gmail sent successfully (attempt {$attempt->attempt_no})."
        );
    }

    /** @return list<string> */
    private function parseExtraCc(?string $raw): array
    {
        if (! filled($raw)) {
            return [];
        }

        $emails = collect(preg_split('/[\s,;]+/', trim($raw)) ?: [])
            ->filter()
            ->map(fn ($email) => mb_strtolower(trim((string) $email)))
            ->unique()
            ->values();

        foreach ($emails as $email) {
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new DomainException("Invalid extra CC email: {$email}");
            }
        }

        if ($emails->count() > 20) {
            throw new DomainException('A maximum of 20 extra CC addresses is allowed.');
        }

        /** @var list<string> $result */
        $result = $emails->all();

        return $result;
    }
}
