<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NotificationController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'unread' => ['nullable', 'boolean'],
        ]);

        $notifications = $this->visibleQuery($request)
            ->when($request->boolean('unread'), fn ($query) => $query->whereNull('read_at'))
            ->latest()
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();

        return response()->json($notifications);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'data' => [
                'count' => $this->visibleQuery($request)->whereNull('read_at')->count(),
            ],
        ]);
    }

    public function markRead(Request $request, Notification $notification): JsonResponse
    {
        if (! $notification->isVisibleTo($request->user())) {
            $this->auditLogger->failed($request, 'mark_read', 'notification', 'Notification not visible to user', 'User mencoba menandai notifikasi yang tidak dapat diakses.', [
                'notification_id' => $notification->id,
            ], httpStatus: Response::HTTP_FORBIDDEN);

            return response()->json(['message' => 'Notifikasi tidak dapat diakses.'], Response::HTTP_FORBIDDEN);
        }

        $notification->update(['read_at' => $notification->read_at ?? now()]);

        $this->auditLogger->success($request, 'mark_read', 'notification', 'Notifikasi ditandai sudah dibaca.', [
            'notification_id' => $notification->id,
            'type' => $notification->type,
        ]);

        return response()->json([
            'message' => 'Notifikasi ditandai sudah dibaca.',
            'data' => $notification->fresh(),
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $count = $this->visibleQuery($request)
            ->whereNull('read_at')
            ->update(['read_at' => now(), 'updated_at' => now()]);

        $this->auditLogger->success($request, 'mark_all_read', 'notification', 'Semua notifikasi ditandai sudah dibaca.', [
            'count' => $count,
        ]);

        return response()->json([
            'message' => 'Semua notifikasi ditandai sudah dibaca.',
            'data' => ['count' => $count],
        ]);
    }

    private function visibleQuery(Request $request)
    {
        $user = $request->user();

        return Notification::query()
            ->where(function ($query) use ($user): void {
                $query->where('user_id', $user->id)
                    ->orWhere('role_target', $user->role);
            });
    }
}
