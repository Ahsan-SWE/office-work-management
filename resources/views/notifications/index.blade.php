@extends(auth()->user()->hasRole('SUPER_ADMIN') ? 'layouts.admin' : 'layouts.role')

@section('page-title', 'Notifications')
@section('page-subtitle', 'Unread and attention-required items')

@section('content')
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div class="flex gap-2">
            <a href="{{ route('notifications.index') }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold">All</a>
            <a href="{{ route('notifications.index', ['unread' => 1]) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold">
                Unread ({{ $unreadCount }})
            </a>
            <a href="{{ route('notifications.index', ['attention' => 1]) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold">
                Attention ({{ $attentionCount }})
            </a>
        </div>

        <form method="POST" action="{{ route('notifications.read-all') }}">
            @csrf
            <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Mark all read</button>
        </form>
    </div>

    <div class="divide-y divide-slate-100 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        @forelse($notifications as $notification)
            <div class="px-5 py-4 {{ $notification->is_read ? '' : 'bg-slate-50' }}">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="font-semibold">{{ $notification->title }}</h2>
                            @if(!$notification->is_read)
                                <span class="rounded-full bg-blue-50 px-2 py-0.5 text-xs font-semibold text-blue-700">Unread</span>
                            @endif
                            @if($notification->requires_action && !$notification->action_completed_at)
                                <span class="rounded-full bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-700">Attention Needed</span>
                            @endif
                        </div>

                        @if($notification->message)
                            <p class="mt-2 text-sm text-slate-600">{{ $notification->message }}</p>
                        @endif

                        <p class="mt-2 text-xs text-slate-400">{{ $notification->created_at->format('Y-m-d H:i') }}</p>
                    </div>

                    @if(!$notification->is_read)
                        <form method="POST" action="{{ route('notifications.read', $notification) }}">
                            @csrf
                            <button class="whitespace-nowrap text-sm font-semibold">Mark read</button>
                        </form>
                    @endif
                </div>
            </div>
        @empty
            <p class="px-5 py-10 text-center text-sm text-slate-500">No notifications yet.</p>
        @endforelse
    </div>

    <div class="mt-5">{{ $notifications->links() }}</div>
@endsection
