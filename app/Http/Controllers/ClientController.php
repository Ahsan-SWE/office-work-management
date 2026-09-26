<?php

namespace App\Http\Controllers;

use App\Actions\Clients\ChangeClientStatusAction;
use App\Actions\Clients\CreateClientAction;
use App\Actions\Clients\UpdateClientAction;
use App\Enums\ClientStatus;
use App\Models\Client;
use App\Models\Tier;
use App\Support\Clients\ClientDuplicateDetector;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ClientController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('clients.view_all'), 403);

        $query = Client::query()
            ->with('currentTier:id,name');

        if ($request->filled('q')) {
            $needle = '%'.mb_strtolower(trim($request->string('q')->toString())).'%';

            $query->where(function ($q) use ($needle) {
                $q->whereRaw('LOWER(name) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(client_code) LIKE ?', [$needle]);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('tier_id')) {
            $query->where('current_tier_id', $request->integer('tier_id'));
        }

        return view('clients.index', [
            'clients' => $query
                ->orderByRaw("CASE WHEN status = 'ACTIVE' THEN 0 ELSE 1 END")
                ->orderBy('name')
                ->paginate(30)
                ->withQueryString(),
            'tiers' => Tier::query()->orderBy('name')->get(),
            'statuses' => ClientStatus::cases(),
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('clients.create'), 403);

        return view('clients.form', [
            'client' => new Client([
                'status' => ClientStatus::ACTIVE,
            ]),
            'tiers' => Tier::query()->where('is_active', true)->orderBy('name')->get(),
            'isEdit' => false,
        ]);
    }

    public function store(
        Request $request,
        ClientDuplicateDetector $duplicates,
        CreateClientAction $action
    ): RedirectResponse {
        abort_unless($request->user()->can('clients.create'), 403);

        $data = $this->validated($request, false);

        $candidates = $duplicates->find($data['name']);

        if ($candidates->isNotEmpty() && ! $request->boolean('confirm_duplicate')) {
            return back()
                ->withInput()
                ->with('duplicate_warning', $candidates->all());
        }

        $client = $action->handle($data, $request->user()->id);

        return redirect()
            ->route('clients.show', $client)
            ->with('success', 'Client created successfully.');
    }

    public function show(Request $request, Client $client): View
    {
        abort_unless($request->user()->can('clients.view_all'), 403);

        $client->load([
            'currentTier:id,name',
            'creator:id,name,email',
            'updater:id,name,email',
            'sheetUrlHistories.changer:id,name,email',
            'tierHistories.tier:id,name',
            'tierHistories.changer:id,name,email',
        ]);

        $workOrders = $client->workOrders()
            ->with([
                'creator:id,name,email',
                'assignments.employee:id,name,email',
                'assignments.section:id,name',
            ])
            ->limit(20)
            ->get();

        return view('clients.show', compact('client', 'workOrders'));
    }

    public function edit(Request $request, Client $client): View
    {
        abort_unless($request->user()->can('clients.update'), 403);

        return view('clients.form', [
            'client' => $client,
            'tiers' => Tier::query()->where('is_active', true)->orderBy('name')->get(),
            'isEdit' => true,
        ]);
    }

    public function update(
        Request $request,
        Client $client,
        ClientDuplicateDetector $duplicates,
        UpdateClientAction $action
    ): RedirectResponse {
        abort_unless($request->user()->can('clients.update'), 403);

        $isSuperAdmin = $request->user()->hasRole('SUPER_ADMIN');
        $data = $this->validated($request, $isSuperAdmin);

        $candidates = $duplicates->find($data['name'], $client->id);

        if ($candidates->isNotEmpty() && ! $request->boolean('confirm_duplicate')) {
            return back()
                ->withInput()
                ->with('duplicate_warning', $candidates->all());
        }

        $action->handle(
            $client,
            $data,
            $request->user()->id,
            $isSuperAdmin
        );

        return redirect()
            ->route('clients.show', $client)
            ->with('success', 'Client updated successfully.');
    }

    public function deactivate(
        Request $request,
        Client $client,
        ChangeClientStatusAction $action
    ): RedirectResponse {
        abort_unless($request->user()->can('clients.deactivate'), 403);

        $action->handle($client, ClientStatus::INACTIVE, $request->user()->id);

        return back()->with('success', 'Client deactivated. History has been preserved.');
    }

    public function reactivate(
        Request $request,
        Client $client,
        ChangeClientStatusAction $action
    ): RedirectResponse {
        abort_unless($request->user()->can('clients.deactivate'), 403);

        $action->handle($client, ClientStatus::ACTIVE, $request->user()->id);

        return back()->with('success', 'Client reactivated.');
    }

    private function validated(Request $request, bool $allowTierCorrection): array
    {
        $googleSheetRule = function (string $attribute, mixed $value, Closure $fail): void {
            $host = strtolower((string) parse_url((string) $value, PHP_URL_HOST));
            $path = (string) parse_url((string) $value, PHP_URL_PATH);

            if ($host !== 'docs.google.com' || ! str_starts_with($path, '/spreadsheets/')) {
                $fail('The Google Sheet URL must be a docs.google.com/spreadsheets link.');
            }
        };

        $rules = [
            'name' => ['required', 'string', 'max:220'],
            'google_sheet_url' => ['required', 'url', 'max:4000', $googleSheetRule],
            'start_date' => ['nullable', 'date'],
            'expected_end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'confirm_duplicate' => ['nullable', 'boolean'],
        ];

        if (! $request->route('client') || $allowTierCorrection) {
            $rules['current_tier_id'] = [
                'nullable',
                Rule::exists('tiers', 'id')->where('is_active', true),
            ];
        }

        return $request->validate($rules);
    }
}
