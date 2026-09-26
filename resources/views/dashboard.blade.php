<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Office Work Management - Dashboard</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900">
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
            <div>
                <p class="text-sm font-semibold">Office Work Management</p>
                <p class="text-xs text-slate-500">Milestone 1 foundation</p>
            </div>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-50">
                    Sign out
                </button>
            </form>
        </div>
    </header>

    <main class="mx-auto max-w-6xl px-6 py-10">
        <div class="rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
            <p class="text-sm text-slate-500">Signed in as</p>
            <h1 class="mt-1 text-2xl font-bold">{{ $user->name }}</h1>
            <p class="mt-1 text-sm text-slate-600">{{ $user->email }}</p>

            <div class="mt-6 grid gap-4 sm:grid-cols-2">
                <div class="rounded-xl bg-slate-50 p-5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Role</p>
                    <p class="mt-2 text-lg font-semibold">{{ $user->getRoleNames()->first() ?? 'UNASSIGNED' }}</p>
                </div>

                <div class="rounded-xl bg-slate-50 p-5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Account status</p>
                    <p class="mt-2 text-lg font-semibold">{{ $user->status->value }}</p>
                </div>
            </div>

            <p class="mt-8 text-sm text-slate-500">
                Role-specific dashboards and administration screens are added in the next Milestone 1 batch.
            </p>
        </div>
    </main>
</body>
</html>
