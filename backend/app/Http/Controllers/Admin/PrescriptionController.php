<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Prescription;
use App\Services\AuditLogger;
use App\Services\NotificationService;
use App\Services\PrescriptionReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class PrescriptionController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly PrescriptionReviewService $reviewService,
        private readonly NotificationService $notificationService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'keyword' => ['nullable', 'string', 'max:255'],
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(Prescription::STATUSES)],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $keyword = $filters['keyword'] ?? $filters['search'] ?? null;

        $prescriptions = Prescription::query()
            ->with([
                'user:id,name,email,role',
                'pharmacist:id,name,email,role',
                'order:id,user_id,order_number,status,payment_status,total,customer_name,created_at',
            ])
            ->when(isset($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->when(isset($filters['date_from']), fn ($query) => $query->whereDate('created_at', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn ($query) => $query->whereDate('created_at', '<=', $filters['date_to']))
            ->when($keyword, function ($query) use ($keyword): void {
                $value = '%'.$keyword.'%';
                $query->where(function ($search) use ($value): void {
                    $search->whereHas('order', fn ($orderQuery) => $orderQuery
                        ->whereLike('order_number', $value, caseSensitive: false)
                        ->orWhereLike('customer_name', $value, caseSensitive: false))
                        ->orWhereHas('user', fn ($userQuery) => $userQuery
                            ->whereLike('name', $value, caseSensitive: false)
                            ->orWhereLike('email', $value, caseSensitive: false))
                        ->orWhereHas('order.items', fn ($itemQuery) => $itemQuery
                            ->whereLike('medicine_name', $value, caseSensitive: false));
                });
            })
            ->latest()
            ->paginate($filters['per_page'] ?? 10)
            ->through(fn (Prescription $prescription) => $this->prescriptionPayload($prescription))
            ->withQueryString();

        return response()->json($prescriptions);
    }

    public function show(Prescription $prescription): JsonResponse
    {
        return response()->json([
            'data' => $this->prescriptionPayload($prescription->load($this->detailRelations())),
        ]);
    }

    public function approve(Request $request, Prescription $prescription): JsonResponse
    {
        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $updated = $this->reviewService->approve($prescription, $request->user(), $data['notes'] ?? null);
        } catch (InvalidArgumentException $exception) {
            $this->auditLogger->failed($request, 'approve', 'prescription', 'Prescription approval rejected', $exception->getMessage(), [
                'prescription_id' => $prescription->id,
                'requested_notes' => $data['notes'] ?? null,
            ], httpStatus: Response::HTTP_UNPROCESSABLE_ENTITY);

            return response()->json(['message' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->auditLogger->success($request, 'approve', 'prescription', "Resep untuk pesanan {$updated->order->order_number} disetujui.", [
            'prescription_id' => $updated->id,
            'order_id' => $updated->order_id,
            'order_number' => $updated->order->order_number,
            'order_status' => $updated->order->status,
        ]);
        $this->notificationService->notifyOrderStatusChanged($updated->order, Order::STATUS_WAITING_PRESCRIPTION_REVIEW);

        return response()->json([
            'message' => 'Resep berhasil disetujui.',
            'data' => $this->prescriptionPayload($updated),
        ]);
    }

    public function reject(Request $request, Prescription $prescription): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        try {
            $updated = $this->reviewService->reject($prescription, $request->user(), $data['reason']);
        } catch (InvalidArgumentException $exception) {
            $this->auditLogger->failed($request, 'reject', 'prescription', 'Prescription rejection rejected', $exception->getMessage(), [
                'prescription_id' => $prescription->id,
            ], httpStatus: Response::HTTP_UNPROCESSABLE_ENTITY);

            return response()->json(['message' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->auditLogger->success($request, 'reject', 'prescription', "Resep untuk pesanan {$updated->order->order_number} ditolak.", [
            'prescription_id' => $updated->id,
            'order_id' => $updated->order_id,
            'order_number' => $updated->order->order_number,
            'order_status' => $updated->order->status,
        ]);
        $this->notificationService->notifyOrderStatusChanged($updated->order, Order::STATUS_WAITING_PRESCRIPTION_REVIEW);

        return response()->json([
            'message' => 'Resep berhasil ditolak.',
            'data' => $this->prescriptionPayload($updated),
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function detailRelations(): array
    {
        return [
            'user:id,name,email,role',
            'pharmacist:id,name,email,role',
            'order.user:id,name,email,role',
            'order.items.medicine.category',
            'order.items.batchUsages.medicineBatch',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function prescriptionPayload(Prescription $prescription): array
    {
        $payload = $prescription->toArray();
        $payload['reviewed_by'] = $prescription->pharmacist ? $prescription->pharmacist->only(['id', 'name', 'email', 'role']) : null;

        return $payload;
    }
}
