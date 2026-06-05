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
            ->orderByRaw('CASE WHEN read_at IS NULL THEN 0 ELSE 1 END')
            ->latest('created_at')
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();

        $notifications->getCollection()->transform(
            fn (Notification $notification): array => $this->serializeNotification($request, $notification),
        );

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
            'data' => $this->serializeNotification($request, $notification->fresh()),
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

    /** @return array<string, mixed> */
    private function serializeNotification(Request $request, Notification $notification): array
    {
        $payload = [
            'id' => $notification->id,
            'title' => $notification->title,
            'message' => $notification->message,
            'type' => $notification->type,
            'severity' => $notification->severity,
            'is_read' => $notification->is_read,
            'read_at' => $notification->read_at,
            'created_at' => $notification->created_at,
            'target_url' => null,
        ];
        $orderId = data_get($notification->data, 'order_id');

        if ($orderId) {
            $payload['target_url'] = match ($request->user()->role) {
                'pelanggan' => "/my-orders/{$orderId}",
                'apoteker' => '/admin/prescription',
                default => "/admin/orders?order={$orderId}",
            };
        }

        return $payload;
    }
}
