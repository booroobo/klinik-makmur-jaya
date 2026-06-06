<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\NotificationService;
use App\Services\OrderWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class OrderController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly OrderWorkflowService $workflow,
        private readonly NotificationService $notificationService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'keyword' => ['nullable', 'string', 'max:255'],
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(Order::STATUSES)],
            'payment_status' => ['nullable', Rule::in(Order::PAYMENT_STATUSES)],
            'fulfillment_type' => ['nullable', Rule::in(Order::FULFILLMENT_METHODS)],
            'fulfillment_method' => ['nullable', Rule::in(Order::FULFILLMENT_METHODS)],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $keyword = $filters['keyword'] ?? $filters['search'] ?? null;
        $fulfillment = $filters['fulfillment_type'] ?? $filters['fulfillment_method'] ?? null;

        $orders = Order::query()
            ->with(['user:id,name,email', 'prescription'])
            ->withCount('items')
            ->when($keyword, function ($query) use ($keyword): void {
                $value = '%'.$keyword.'%';
                $query->where(function ($search) use ($value): void {
                    $search->whereLike('order_number', $value, caseSensitive: false)
                        ->orWhereLike('customer_name', $value, caseSensitive: false)
                        ->orWhereLike('customer_phone', $value, caseSensitive: false)
                        ->orWhereHas('user', fn ($userQuery) => $userQuery
                            ->whereLike('name', $value, caseSensitive: false)
                            ->orWhereLike('email', $value, caseSensitive: false));
                });
            })
            ->when(isset($filters['status']), fn ($query) => $filters['status'] === Order::STATUS_WAITING_PRESCRIPTION_REVIEW
                ? $query->whereIn('status', [Order::STATUS_WAITING_PRESCRIPTION_REVIEW, Order::STATUS_LEGACY_WAITING_PRESCRIPTION])
                : $query->where('status', $filters['status']))
            ->when(isset($filters['payment_status']), fn ($query) => $query->where('payment_status', $filters['payment_status']))
            ->when($fulfillment, fn ($query) => $query->where('fulfillment_method', $fulfillment))
            ->when(isset($filters['date_from']), fn ($query) => $query->whereDate('created_at', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn ($query) => $query->whereDate('created_at', '<=', $filters['date_to']))
            ->latest()
            ->paginate($filters['per_page'] ?? 10)
            ->withQueryString();

        return response()->json($orders);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        $order->load($this->detailRelations());

        return response()->json([
            'data' => $this->orderPayload($order, $request->user()),
        ]);
    }

    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(Order::STATUSES)],
        ]);
        $before = $order->only(['status', 'payment_status']);

        try {
            $updated = DB::transaction(fn () => $this->workflow->updateStatus($order, $data['status'], $request->user()));
        } catch (InvalidArgumentException $exception) {
            $this->auditLogger->failed($request, 'update_status', 'order', 'Invalid order status transition', $exception->getMessage(), [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'before' => $before,
                'requested_status' => $data['status'],
            ], httpStatus: Response::HTTP_UNPROCESSABLE_ENTITY);

            return response()->json(['message' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->auditLogger->success($request, 'update_status', 'order', "Status pesanan {$updated->order_number} diperbarui.", [
            'order_id' => $updated->id,
            'order_number' => $updated->order_number,
            'before' => $before,
            'after' => $updated->only(['status', 'payment_status']),
        ]);
        $this->notificationService->notifyOrderStatusChanged($updated, $before['status'] ?? null);

        return response()->json([
            'message' => 'Status pesanan berhasil diperbarui.',
            'data' => $this->orderPayload($updated->load($this->detailRelations()), $request->user()),
        ]);
    }

    public function updatePayment(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate([
            'payment_status' => ['required', Rule::in(Order::PAYMENT_STATUSES)],
        ]);
        $before = $order->only(['status', 'payment_status']);

        try {
            $updated = DB::transaction(fn () => $this->workflow->updatePayment($order, $data['payment_status']));
        } catch (InvalidArgumentException $exception) {
            $this->auditLogger->failed($request, 'update_payment', 'order', 'Invalid order payment transition', $exception->getMessage(), [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'before' => $before,
                'requested_payment_status' => $data['payment_status'],
            ], httpStatus: Response::HTTP_UNPROCESSABLE_ENTITY);

            return response()->json(['message' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->auditLogger->success($request, 'update_payment', 'order', "Pembayaran pesanan {$updated->order_number} diperbarui.", [
            'order_id' => $updated->id,
            'order_number' => $updated->order_number,
            'before' => $before,
            'after' => $updated->only(['status', 'payment_status']),
        ]);

        if (($before['status'] ?? null) !== $updated->status) {
            $this->notificationService->notifyOrderStatusChanged($updated, $before['status'] ?? null);
        }

        return response()->json([
            'message' => 'Status pembayaran berhasil diperbarui.',
            'data' => $this->orderPayload($updated->load($this->detailRelations()), $request->user()),
        ]);
    }

    public function cancel(Request $request, Order $order): JsonResponse
    {
        $before = $order->only(['status', 'payment_status']);

        try {
            $updated = DB::transaction(fn () => $this->workflow->cancel($order));
        } catch (InvalidArgumentException $exception) {
            $this->auditLogger->failed($request, 'cancel', 'order', 'Invalid order cancellation', $exception->getMessage(), [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'before' => $before,
            ], httpStatus: Response::HTTP_UNPROCESSABLE_ENTITY);

            return response()->json(['message' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->auditLogger->success($request, 'cancel', 'order', "Pesanan {$updated->order_number} dibatalkan.", [
            'order_id' => $updated->id,
            'order_number' => $updated->order_number,
            'before' => $before,
            'after' => $updated->only(['status', 'payment_status']),
        ]);
        $this->notificationService->notifyOrderStatusChanged($updated, $before['status'] ?? null);

        return response()->json([
            'message' => 'Pesanan berhasil dibatalkan.',
            'data' => $this->orderPayload($updated->load($this->detailRelations()), $request->user()),
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function detailRelations(): array
    {
        return [
            'user:id,name,email',
            'items.medicine.category',
            'items.variant',
            'items.batchUsages.medicineBatch.variant',
            'prescription',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function orderPayload(Order $order, ?User $actor = null): array
    {
        return $order->toArray() + [
            'normalized_status' => $order->normalizedStatus(),
            'allowed_next_statuses' => $this->workflow->allowedNextStatuses($order, $actor),
        ];
    }
}
