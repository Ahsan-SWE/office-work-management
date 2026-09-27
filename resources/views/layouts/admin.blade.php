<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Office Work Management' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900">
<div class="min-h-screen lg:grid lg:grid-cols-[260px_1fr]">
    <aside class="border-b border-slate-200 bg-slate-950 text-slate-100 lg:min-h-screen lg:border-b-0 lg:border-r lg:border-slate-800">
        <div class="flex items-center justify-between px-5 py-5 lg:block">
            <div>
                <p class="font-bold">Office Work Management</p>
                <p class="mt-1 text-xs text-slate-400">Super Admin</p>
            </div>
        </div>
        <nav class="grid gap-1 px-3 pb-5 text-sm sm:grid-cols-4 lg:grid-cols-1">
            @php
                $links = [
                    ['admin.dashboard', 'Dashboard'],
                    ['clients.index', 'Clients'],
                    ['work.index', 'All Work'],
                    ['admin.teams.index', 'Teams'],
                    ['admin.allowed-emails.index', 'Allowed Gmail'],
                    ['admin.users.index', 'Users'],
['admin.performance.index', 'Performance'],
                    ['admin.qc-escalations.index', 'QC Escalations'],
                    ['admin.audit-logs.index', 'Audit Logs'],
                    ['admin.settings.index', 'Settings'],
                    ['notifications.index', 'Notifications'],
                ];
            @endphp
            @foreach ($links as [$routeName, $label])
                @php
                    $pattern = str_ends_with($routeName, '.index')
                        ? str_replace('.index', '.*', $routeName)
                        : $routeName;
                @endphp
                <a
                    href="{{ route($routeName) }}"
                    class="rounded-lg px-3 py-2.5 font-medium transition
                    {{ request()->routeIs($pattern) || request()->routeIs($routeName)
                        ? 'bg-white text-slate-950'
                        : 'text-slate-300 hover:bg-slate-900 hover:text-white' }}"
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
                    <h1 class="text-lg font-bold">@yield('page-title', $header ?? 'Super Admin')</h1>
                    <p class="mt-0.5 text-sm text-slate-500">@yield('page-subtitle', $subheader ?? '')</p>
                </div>
                <div class="flex items-center gap-3">
                    <div class="hidden text-right sm:block">
                        <p class="text-sm font-semibold">{{ auth()->user()->name }}</p>
                        <p class="text-xs text-slate-500">{{ auth()->user()->email }}</p>
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
            @if (session('success'))
                <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ session('success') }}
                </div>
            @endif
            @if (session('warning'))
                <div class="mb-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    {{ session('warning') }}
                </div>
            @endif
            @if ($errors->any())
                <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <p class="font-semibold">Please fix the following:</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5">
                        @foreach ($errors->all() as $error)
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
