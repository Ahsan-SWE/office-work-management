<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Office Work Management' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900">
@php
    $user = auth()->user();
    $role = $user->getRoleNames()->first();

    $nav = match ($role) {
        'TEAM_LEADER' => [
            ['team-leader.dashboard', 'Dashboard'],
            ['team-leader.members.index', 'My Team'],
            ['team-leader.placeholder', 'Team Work', ['section' => 'team-work']],
            ['team-leader.placeholder', 'Performance', ['section' => 'performance']],
            ['team-leader.placeholder', 'Improvement Sessions', ['section' => 'improvement-sessions']],
            ['team-leader.placeholder', 'Monthly Reports', ['section' => 'monthly-reports']],
            ['notifications.index', 'Notifications'],
        ],
        'EMPLOYEE' => [
            ['employee.dashboard', 'Dashboard'],
            ['employee.placeholder', 'My Work', ['section' => 'my-work']],
            ['employee.placeholder', 'Work History', ['section' => 'work-history']],
            ['employee.placeholder', 'My Performance', ['section' => 'my-performance']],
            ['employee.placeholder', 'Improvement Sessions', ['section' => 'improvement-sessions']],
            ['notifications.index', 'Notifications'],
        ],
        'QC' => [
            ['qc.dashboard', 'QC Dashboard'],
            ['qc.placeholder', 'Waiting for QC', ['section' => 'waiting-qc']],
            ['qc.placeholder', 'Rework Resubmitted', ['section' => 'rework-resubmitted']],
            ['qc.placeholder', 'Reviewed History', ['section' => 'reviewed-history']],
            ['notifications.index', 'Notifications'],
        ],
        default => [],
    };
@endphp

<div class="min-h-screen lg:grid lg:grid-cols-[260px_1fr]">
    <aside class="border-b border-slate-200 bg-slate-950 text-slate-100 lg:min-h-screen lg:border-b-0 lg:border-r lg:border-slate-800">
        <div class="px-5 py-5">
            <p class="font-bold">Office Work Management</p>
            <p class="mt-1 text-xs text-slate-400">{{ str_replace('_', ' ', $role ?? '') }}</p>
        </div>

        <nav class="grid gap-1 px-3 pb-5 text-sm sm:grid-cols-4 lg:grid-cols-1">
            @foreach($nav as $item)
                @php
                    [$routeName, $label] = $item;
                    $params = $item[2] ?? [];
                    $active = request()->routeIs($routeName)
                        && (empty($params) || request()->route('section') === ($params['section'] ?? null));
                @endphp
                <a
                    href="{{ route($routeName, $params) }}"
                    class="rounded-lg px-3 py-2.5 font-medium transition
                    {{ $active ? 'bg-white text-slate-950' : 'text-slate-300 hover:bg-slate-900 hover:text-white' }}"
                >
                    {{ $label }}
                </a>
            @endforeach
        </nav>
    </aside>

    <div>
        <header class="border-b border-slate-200 bg-white">
            <div class="flex items-center justify-between gap-4 px-5 py-4 lg:px-8">
                <div>
                    <h1 class="text-lg font-bold">@yield('page-title', 'Dashboard')</h1>
                    <p class="mt-0.5 text-sm text-slate-500">@yield('page-subtitle')</p>
                </div>

                <div class="flex items-center gap-3">
                    <div class="hidden text-right sm:block">
                        <p class="text-sm font-semibold">{{ $user->name }}</p>
                        <p class="text-xs text-slate-500">{{ $user->email }}</p>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium hover:bg-slate-50">
                            Sign out
                        </button>
                    </form>
                </div>
            </div>
        </header>

        <main class="px-5 py-6 lg:px-8">
            @if(session('success'))
                <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ session('success') }}
                </div>
            @endif

            @if($errors->any())
                <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <ul class="list-disc space-y-1 pl-5">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</div>
</body>
</html>
