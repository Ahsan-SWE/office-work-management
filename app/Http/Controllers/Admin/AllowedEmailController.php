<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Users\SaveAllowedEmailAction;
use App\Enums\AllowedEmailStatus;
use App\Enums\QcScope;
use App\Enums\RoleName;
use App\Enums\TeamStatus;
use App\Http\Controllers\Controller;
use App\Models\AllowedEmail;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AllowedEmailController extends Controller
{
    public function index(Request $request): View
    {
        $query = AllowedEmail::query()
            ->with(['preselectedTeam:id,name', 'registeredUser:id,name,email'])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('q')) {
            $search = '%'.mb_strtolower($request->string('q')->toString()).'%';
            $query->whereRaw('LOWER(email) LIKE ?', [$search]);
        }

        return view('admin.allowed-emails.index', [
            'allowedEmails' => $query->paginate(25)->withQueryString(),
            'statuses' => AllowedEmailStatus::cases(),
        ]);
    }

    public function create(): View
    {
        return $this->form(new AllowedEmail());
    }

    public function store(Request $request, SaveAllowedEmailAction $action): RedirectResponse
    {
        $allowed = $action->handle($this->validated($request));

        return redirect()
            ->route('admin.allowed-emails.edit', $allowed)
            ->with('success', 'Approved Gmail/email added.');
    }

    public function edit(AllowedEmail $allowedEmail): View
    {
        return $this->form($allowedEmail);
    }

    public function update(
        Request $request,
        AllowedEmail $allowedEmail,
        SaveAllowedEmailAction $action
    ): RedirectResponse {
        $action->handle($this->validated($request, $allowedEmail), $allowedEmail);

        return back()->with('success', 'Approved email updated.');
    }

    public function disable(
        AllowedEmail $allowedEmail,
        SaveAllowedEmailAction $action
    ): RedirectResponse {
        $action->setEnabled($allowedEmail, false);

        return back()->with('success', 'Approved email disabled.');
    }

    public function enable(
        AllowedEmail $allowedEmail,
        SaveAllowedEmailAction $action
    ): RedirectResponse {
        $action->setEnabled($allowedEmail, true);

        return back()->with('success', 'Approved email enabled.');
    }

    private function form(AllowedEmail $allowedEmail): View
    {
        return view('admin.allowed-emails.form', [
            'allowedEmail' => $allowedEmail,
            'roles' => [
                RoleName::TEAM_LEADER,
                RoleName::EMPLOYEE,
                RoleName::QC,
            ],
            'qcScopes' => QcScope::cases(),
            'teams' => Team::query()
                ->where('status', TeamStatus::ACTIVE->value)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    private function validated(Request $request, ?AllowedEmail $allowedEmail = null): array
    {
        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
        ]);

        return $request->validate([
            'email' => [
                'required',
                'email:rfc',
                'max:320',
                Rule::unique('allowed_emails', 'email')->ignore($allowedEmail?->id),
                Rule::unique('users', 'email'),
            ],
            'preselected_role' => [
                'required',
                Rule::in([
                    RoleName::TEAM_LEADER->value,
                    RoleName::EMPLOYEE->value,
                    RoleName::QC->value,
                ]),
            ],
            'preselected_team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'preselected_qc_scope' => [
                'nullable',
                Rule::in(array_map(fn ($case) => $case->value, QcScope::cases())),
            ],
        ]);
    }
}
