<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Users\ChangeUserStatusAction;
use App\Actions\Users\SetUserPermissionOverrideAction;
use App\Actions\Users\SyncEmployeeCapabilitiesAction;
use App\Actions\Users\SyncQcScopesAction;
use App\Enums\Capability;
use App\Enums\PermissionDecision;
use App\Enums\QcScope;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserPermissionOverride;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $query = User::query()
            ->with(['primaryTeam:id,name', 'roles:id,name'])
            ->orderBy('name');

        if ($request->filled('q')) {
            $search = '%'.mb_strtolower($request->string('q')->toString()).'%';
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(name) LIKE ?', [$search])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$search]);
            });
        }

        if ($request->filled('role')) {
            $query->role($request->string('role')->toString());
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        return view('admin.users.index', [
            'users' => $query->paginate(25)->withQueryString(),
            'roles' => RoleName::cases(),
            'statuses' => UserStatus::cases(),
        ]);
    }

    public function show(User $user): View
    {
        $user->load([
            'roles',
            'primaryTeam',
            'capabilities',
            'qcScopes' => fn ($q) => $q->whereNull('revoked_at'),
            'permissionOverrides',
        ]);

        return view('admin.users.show', [
            'managedUser' => $user,
            'statuses' => UserStatus::cases(),
            'capabilities' => Capability::cases(),
            'qcScopes' => QcScope::cases(),
            'permissions' => Permission::query()->orderBy('name')->get(),
            'decisions' => PermissionDecision::cases(),
        ]);
    }

    public function updateStatus(
        Request $request,
        User $user,
        ChangeUserStatusAction $action
    ): RedirectResponse {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_map(fn ($case) => $case->value, UserStatus::cases()))],
        ]);

        $action->handle($user, UserStatus::from($data['status']));

        return back()->with('success', 'User status updated. Existing sessions were revoked.');
    }

    public function updateCapabilities(
        Request $request,
        User $user,
        SyncEmployeeCapabilitiesAction $action
    ): RedirectResponse {
        abort_unless($user->hasRole(RoleName::EMPLOYEE->value), 422);

        $data = $request->validate([
            'capabilities' => ['array'],
            'capabilities.*' => [
                Rule::in(array_map(fn ($case) => $case->value, Capability::cases())),
            ],
        ]);

        $action->handle($user, $data['capabilities'] ?? []);

        return back()->with('success', 'Employee capabilities updated.');
    }

    public function updateQcScopes(
        Request $request,
        User $user,
        SyncQcScopesAction $action
    ): RedirectResponse {
        abort_unless($user->hasRole(RoleName::QC->value), 422);

        $data = $request->validate([
            'qc_scopes' => ['array', 'min:1'],
            'qc_scopes.*' => [
                Rule::in(array_map(fn ($case) => $case->value, QcScope::cases())),
            ],
        ]);

        $action->handle($user, $data['qc_scopes'] ?? []);

        return back()->with('success', 'QC scopes updated.');
    }

    public function setPermissionOverride(
        Request $request,
        User $user,
        SetUserPermissionOverrideAction $action
    ): RedirectResponse {
        $data = $request->validate([
            'permission_key' => ['required', 'string', 'exists:permissions,name'],
            'decision' => [
                'required',
                Rule::in(array_map(fn ($case) => $case->value, PermissionDecision::cases())),
            ],
        ]);

        $action->set(
            $user,
            $data['permission_key'],
            PermissionDecision::from($data['decision'])
        );

        return back()->with('success', 'Permission override saved.');
    }

    public function removePermissionOverride(
        User $user,
        UserPermissionOverride $override,
        SetUserPermissionOverrideAction $action
    ): RedirectResponse {
        $action->remove($user, $override);

        return back()->with('success', 'Permission override removed.');
    }
}
