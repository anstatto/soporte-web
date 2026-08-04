<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class NotificationController extends Controller
{
    public function index()
    {
        $notifications = Auth::user()
            ->unreadNotifications()
            ->latest()
            ->limit(40)
            ->get()
            ->map(fn ($n) => $this->mapNotification($n));

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => Auth::user()->unreadNotifications()->count(),
        ]);
    }

    public function page(Request $request)
    {
        $filter = $request->input('filter', 'all');

        $query = Auth::user()->notifications()->latest();

        if ($filter === 'unread') {
            $query->whereNull('read_at');
        } elseif ($filter === 'read') {
            $query->whereNotNull('read_at');
        }

        $notifications = $query->limit(80)->get()->map(fn ($n) => $this->mapNotification($n));

        return Inertia::render('Notifications/Index', [
            'notifications' => $notifications,
            'filter' => in_array($filter, ['all', 'unread', 'read'], true) ? $filter : 'all',
        ]);
    }

    public function markAsRead(string $id)
    {
        $notification = Auth::user()->notifications()->where('id', $id)->first();
        $notification?->markAsRead();

        if (request()->header('X-Inertia')) {
            return back();
        }

        return response()->json([
            'ok' => true,
            'unread_count' => Auth::user()->unreadNotifications()->count(),
        ]);
    }

    public function markTicketRead(Request $request)
    {
        $ticketId = (int) $request->validate([
            'ticket_id' => 'required|integer',
        ])['ticket_id'];

        Auth::user()
            ->unreadNotifications
            ->filter(fn ($n) => (int) ($n->data['ticket_id'] ?? 0) === $ticketId)
            ->each->markAsRead();

        \App\Support\ChatInbox::markTicketRead(Auth::user(), $ticketId);

        return response()->json([
            'ok' => true,
            'unread_count' => Auth::user()->unreadNotifications()->count(),
        ]);
    }

    public function markAllRead()
    {
        Auth::user()->unreadNotifications->markAsRead();

        if (request()->header('X-Inertia')) {
            return back()->with('success', 'Notificaciones marcadas como leídas.');
        }

        return response()->json([
            'ok' => true,
            'unread_count' => 0,
        ]);
    }

    protected function mapNotification($n): array
    {
        return [
            'id' => $n->id,
            'type' => $n->data['type'] ?? class_basename($n->type),
            'data' => $n->data,
            'created_at' => $n->created_at?->toIso8601String(),
            'read_at' => $n->read_at?->toIso8601String(),
        ];
    }
}
