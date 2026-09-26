<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Office Work Management - Sign in</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900">
    <main class="mx-auto flex min-h-screen max-w-6xl items-center justify-center px-6 py-12">
        <section class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
            <div class="mb-8">
                <p class="text-sm font-semibold uppercase tracking-wider text-slate-500">Internal Office System</p>
                <h1 class="mt-2 text-3xl font-bold">Sign in</h1>
                <p class="mt-3 text-sm leading-6 text-slate-600">
                    Use the exact Google account approved by your Super Admin.
                </p>
            </div>

            @if (session('error'))
                <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    {{ session('error') }}
                </div>
            @endif

            <a
                href="{{ route('auth.google.redirect') }}"
                class="flex w-full items-center justify-center rounded-lg bg-slate-900 px-5 py-3 font-semibold text-white transition hover:bg-slate-700"
            >
                Continue with Google
            </a>

            <p class="mt-5 text-xs leading-5 text-slate-500">
                Registration is invitation-only. Normal Google login does not request Gmail sending permission.
            </p>
        </section>
    </main>
</body>
</html>
