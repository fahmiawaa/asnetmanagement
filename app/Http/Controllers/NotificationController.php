<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function feed(Request $request): JsonResponse
    {
        $user = $request->user();
        $notifications = $user->notifications()
            ->latest()
            ->limit(10)
            ->get();

        return response()->json([
            'unread_count' => $user->unreadNotifications()->count(),
            'notifications' => $notifications->map(fn ($notification): array => [
                'id' => $notification->id,
                'title' => data_get($notification->data, 'title', 'Notifikasi ASNet'),
                'message' => data_get($notification->data, 'message', ''),
                'url' => data_get($notification->data, 'url'),
                'level' => data_get($notification->data, 'level', 'info'),
                'read' => $notification->read_at !== null,
                'created_at' => $notification->created_at?->toIso8601String(),
                'created_label' => $notification->created_at?->diffForHumans(),
            ])->values(),
        ]);
    }

    public function markRead(Request $request, string $notification): JsonResponse
    {
        $item = $request->user()->notifications()
            ->whereKey($notification)
            ->firstOrFail();

        $item->markAsRead();

        return response()->json(['ok' => true]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update([
            'read_at' => now(),
        ]);

        return response()->json(['ok' => true]);
    }
}
