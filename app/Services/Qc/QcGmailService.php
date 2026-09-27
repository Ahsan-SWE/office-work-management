<?php

namespace App\Services\Qc;

use App\Enums\AuditAction;
use App\Enums\RoleName;
use App\Models\Assignment;
use App\Models\Client;
use App\Models\QcGmailConnection;
use App\Models\QcMajorErrorEmailAttempt;
use App\Models\QcReason;
use App\Models\QcReview;
use App\Models\QcReviewIssue;
use App\Models\QcSubmission;
use App\Models\Team;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\Audit\AuditLogger;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Throwable;

class QcGmailService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function connectionFor(User $qc): ?QcGmailConnection
    {
        return QcGmailConnection::query()->where('user_id', $qc->id)->first();
    }

    /** @param list<string> $extraCc */
    public function sendMajorErrorEmail(QcReview $review, User $qc, array $extraCc = []): QcMajorErrorEmailAttempt
    {
        $review->loadMissing([
            'reviewer:id,name,email',
            'responsibleEmployee.primaryTeam.teamLeader:id,name,email',
            'submission.assignment.workOrder.client:id,client_code,name',
            'submission.assignment.workOrder:id,work_code,client_id',
            'submission.assignment:id,assignment_code,work_order_id',
            'issues.reason:id,name',
        ]);

        /** @var User $employee */
        $employee = $review->responsibleEmployee;
        /** @var Team|null $team */
        $team = $employee->primaryTeam;
        /** @var User|null $teamLeader */
        $teamLeader = $team?->teamLeader()->first();

        /** @var QcSubmission $submission */
        $submission = $review->submission;
        /** @var Assignment $assignment */
        $assignment = $submission->assignment;
        /** @var WorkOrder $workOrder */
        $workOrder = $assignment->workOrder;
        /** @var Client $client */
        $client = $workOrder->client;

        if ($review->reviewed_at === null || ! $review->effectiveIsMajorError()) {
            throw new DomainException('Major Error Gmail can only be sent for a completed Major Error review.');
        }

        if ($review->reviewer_id !== $qc->id) {
            throw new DomainException('The Major Error email must be sent from the reviewing QC user.');
        }

        if ($review->major_error_email_sent_at !== null || $review->major_error_email_sent) {
            throw new DomainException('The Major Error email has already been sent.');
        }

        $connection = QcGmailConnection::query()
            ->where('user_id', $qc->id)
            ->first();

        if (! $connection || ! $connection->isConnected()) {
            throw new DomainException('Connect your QC Gmail before sending this Major Error email.');
        }

        if (mb_strtolower($connection->email) !== mb_strtolower($qc->email)) {
            throw new DomainException('The connected Gmail does not match your Office Work Management account.');
        }

        $recipient = mb_strtolower(trim((string) $employee->email));

        /** @var list<string> $defaultCc */
        $defaultCc = [];

        $teamLeaderEmail = $teamLeader?->email;
        if (is_string($teamLeaderEmail) && trim($teamLeaderEmail) !== '') {
            $defaultCc[] = mb_strtolower(trim($teamLeaderEmail));
        }

        foreach (
            User::query()
                ->role(RoleName::SUPER_ADMIN->value)
                ->where('status', 'ACTIVE')
                ->pluck('email')
                ->all() as $email
        ) {
            $normalized = mb_strtolower(trim((string) $email));

            if ($normalized !== '' && ! in_array($normalized, $defaultCc, true)) {
                $defaultCc[] = $normalized;
            }
        }

        /** @var list<string> $normalizedExtraCc */
        $normalizedExtraCc = [];

        foreach ($extraCc as $email) {
            $normalized = mb_strtolower(trim($email));

            if ($normalized !== '' && ! in_array($normalized, $normalizedExtraCc, true)) {
                $normalizedExtraCc[] = $normalized;
            }
        }

        /** @var list<string> $cc */
        $cc = [];

        foreach ([...$defaultCc, ...$normalizedExtraCc] as $email) {
            if (in_array($email, [mb_strtolower($qc->email), $recipient], true)) {
                continue;
            }

            if (! in_array($email, $cc, true)) {
                $cc[] = $email;
            }
        }

        $subject = sprintf(
            '[Major Error] %s · %s · %s',
            $review->review_code,
            $workOrder->work_code,
            $assignment->assignment_code,
        );

        $issueLines = QcReviewIssue::query()
            ->where('qc_review_id', $review->id)
            ->get()
            ->map(function (QcReviewIssue $issue) {
                /** @var QcReason $reason */
                $reason = $issue->reason()->firstOrFail();

                $line = '- '.$reason->name.' (-'.$issue->negative_points.')';
                if ($issue->site_name) {
                    $line .= ' · '.$issue->site_name;
                }
                if ($issue->comment) {
                    $line .= ' · '.$issue->comment;
                }

                return $line;
            })
            ->implode("\n");

        $body = implode("\n", array_filter([
            "Hello {$employee->name},",
            '',
            "A Major Error was recorded in QC review {$review->review_code}.",
            "Client: {$client->name}",
            "Work: {$workOrder->work_code}",
            "Assignment: {$assignment->assignment_code}",
            "Submission: {$submission->submission_code}",
            "Approved: {$review->effectiveApprovedCount()}",
            "Rework: {$review->effectiveReworkCount()}",
            "Negative: {$review->effectiveNegativePoints()}/5",
            $issueLines ? "\nIssues:\n{$issueLines}" : null,
            $review->review_comment ? "\nQC comment:\n{$review->review_comment}" : null,
            '',
            'Please follow the Office Work Management QC workflow for any required rework.',
            '',
            "Sent by {$qc->name} through Office Work Management.",
        ], fn ($line) => $line !== null));

        $attempt = DB::transaction(function () use (
            $review,
            $qc,
            $recipient,
            $cc,
            $normalizedExtraCc,
            $subject,
            $body
        ) {
            $lockedReview = QcReview::query()->whereKey($review->id)->lockForUpdate()->firstOrFail();

            if ($lockedReview->major_error_email_sent_at !== null || $lockedReview->major_error_email_sent) {
                throw new DomainException('The Major Error email has already been sent.');
            }

            $attemptNo = ((int) QcMajorErrorEmailAttempt::query()
                ->where('qc_review_id', $lockedReview->id)
                ->max('attempt_no')) + 1;

            return QcMajorErrorEmailAttempt::query()->create([
                'qc_review_id' => $lockedReview->id,
                'sender_user_id' => $qc->id,
                'attempt_no' => $attemptNo,
                'recipient_email' => $recipient,
                'cc_emails' => $cc,
                'extra_cc_emails' => $normalizedExtraCc,
                'subject' => $subject,
                'body' => $body,
                'status' => 'PENDING',
                'attempted_at' => now(),
            ]);
        });

        try {
            $accessToken = $this->validAccessToken($connection);
            $response = $this->postMessage(
                accessToken: $accessToken,
                fromEmail: $connection->email,
                fromName: $qc->name,
                recipient: $recipient,
                cc: $cc,
                subject: $subject,
                body: $body,
            );

            if ($response->status() === 401 && filled($connection->refresh_token)) {
                $accessToken = $this->refreshAccessToken($connection);
                $response = $this->postMessage(
                    accessToken: $accessToken,
                    fromEmail: $connection->email,
                    fromName: $qc->name,
                    recipient: $recipient,
                    cc: $cc,
                    subject: $subject,
                    body: $body,
                );
            }

            if (! $response->successful()) {
                throw new DomainException(
                    'Gmail rejected the message (HTTP '.$response->status().'). Reconnect Gmail if authorization was revoked.'
                );
            }

            $gmailMessageId = (string) ($response->json('id') ?? '');

            DB::transaction(function () use ($attempt, $review, $connection, $gmailMessageId) {
                QcMajorErrorEmailAttempt::query()
                    ->whereKey($attempt->id)
                    ->lockForUpdate()
                    ->firstOrFail()
                    ->update([
                        'status' => 'SENT',
                        'gmail_message_id' => $gmailMessageId ?: null,
                        'sent_at' => now(),
                        'error_message' => null,
                    ]);

                QcReview::query()
                    ->whereKey($review->id)
                    ->lockForUpdate()
                    ->firstOrFail()
                    ->update([
                        'major_error_email_sent' => true,
                        'major_error_email_sent_at' => now(),
                    ]);

                $connection->forceFill(['last_error_at' => null, 'last_error_message' => null])->save();
            });

            $this->audit->log(
                AuditAction::QC_MAJOR_ERROR_EMAIL_SENT->value,
                $review,
                oldValues: ['major_error_email_sent' => false],
                newValues: [
                    'major_error_email_sent' => true,
                    'attempt_id' => $attempt->id,
                    'recipient_email' => $recipient,
                    'cc_emails' => $cc,
                    'gmail_message_id' => $gmailMessageId ?: null,
                ],
            );

            return $attempt->fresh();
        } catch (Throwable $exception) {
            QcMajorErrorEmailAttempt::query()
                ->whereKey($attempt->id)
                ->update([
                    'status' => 'FAILED',
                    'error_message' => mb_substr($exception->getMessage(), 0, 2000),
                ]);

            $connection->forceFill([
                'last_error_at' => now(),
                'last_error_message' => mb_substr($exception->getMessage(), 0, 2000),
            ])->save();

            throw $exception instanceof DomainException
                ? $exception
                : new DomainException('Major Error email could not be sent. The QC review is saved and the email can be retried.');
        }
    }

    private function validAccessToken(QcGmailConnection $connection): string
    {
        $expiresAt = $connection->getAttribute('expires_at');

        if ($expiresAt instanceof CarbonInterface && $expiresAt->lte(now()->addMinute())) {
            return $this->refreshAccessToken($connection);
        }

        return (string) $connection->access_token;
    }

    private function refreshAccessToken(QcGmailConnection $connection): string
    {
        if (! filled($connection->refresh_token)) {
            throw new DomainException('QC Gmail authorization has expired. Reconnect Gmail.');
        }

        $response = Http::asForm()
            ->acceptJson()
            ->post('https://oauth2.googleapis.com/token', [
                'client_id' => config('services.google.client_id'),
                'client_secret' => config('services.google.client_secret'),
                'refresh_token' => $connection->refresh_token,
                'grant_type' => 'refresh_token',
            ]);

        if (! $response->successful() || ! filled($response->json('access_token'))) {
            throw new DomainException('QC Gmail authorization could not be refreshed. Reconnect Gmail.');
        }

        $connection->forceFill([
            'access_token' => (string) $response->json('access_token'),
            'expires_at' => now()->addSeconds(max(60, (int) $response->json('expires_in', 3600))),
            'last_refreshed_at' => now(),
            'last_error_at' => null,
            'last_error_message' => null,
        ])->save();

        return (string) $connection->access_token;
    }

    /** @param list<string> $cc */
    private function postMessage(
        string $accessToken,
        string $fromEmail,
        string $fromName,
        string $recipient,
        array $cc,
        string $subject,
        string $body,
    ): Response {
        $email = (new Email)
            ->from(new Address($fromEmail, $fromName))
            ->to(new Address($recipient))
            ->subject($subject)
            ->text($body);

        foreach ($cc as $address) {
            $email->addCc(new Address($address));
        }

        $raw = rtrim(strtr(base64_encode($email->toString()), '+/', '-_'), '=');

        return Http::withToken($accessToken)
            ->acceptJson()
            ->post('https://gmail.googleapis.com/gmail/v1/users/me/messages/send', [
                'raw' => $raw,
            ]);
    }
}
