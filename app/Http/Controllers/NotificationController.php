<?php

namespace App\Http\Controllers;

use App\Models\OfficeNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $query = OfficeNotification::query()
            ->where('user_id', $request->user()->id)
            ->latest();

        if ($request->boolean('attention')) {
            $query->where('requires_action', true)
                ->whereNull('action_completed_at');
        }

        if ($request->boolean('unread')) {
            $query->where('is_read', false);
        }

        return view('notifications.index', [
            'notifications' => $query->paginate(30)->withQueryString(),
            'attentionCount' => OfficeNotification::query()
                ->where('user_id', $request->user()->id)
                ->where('requires_action', true)
                ->whereNull('action_completed_at')
                ->count(),
            'unreadCount' => OfficeNotification::query()
                ->where('user_id', $request->user()->id)
                ->where('is_read', false)
                ->count(),
        ]);
    }

    public function read(Request $request, OfficeNotification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 404);

        if (! $notification->is_read) {
            $notification->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
        }

        return back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        OfficeNotification::query()
            ->where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
                'updated_at' => now(),
            ]);

        return back()->with('success', 'Notifications marked as read.');
    }
}
