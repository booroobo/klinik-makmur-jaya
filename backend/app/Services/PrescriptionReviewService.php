<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Prescription;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PrescriptionReviewService
{
    public function __construct(
        private readonly InventoryService $inventoryService,
    ) {}

    public function approve(Prescription $prescription, User $reviewer, ?string $notes = null): Prescription
    {
        return DB::transaction(function () use ($prescription, $reviewer, $notes): Prescription {
            $locked = $this->lockedPrescription($prescription);
            $order = $locked->order;

            $this->ensurePending($locked);
            $this->ensureReviewableOrder($order);

            $locked->update([
                'status' => Prescription::STATUS_APPROVED,
                'pharmacist_id' => $reviewer->id,
                'pharmacist_notes' => $notes,
                'reviewed_at' => now(),
            ]);

            $order->update([
                'status' => $order->payment_status === Order::PAYMENT_STATUS_PAID
                    ? Order::STATUS_CONFIRMED
                    : Order::STATUS_PENDING_PAYMENT,
            ]);

            return $locked->fresh()->load($this->relations());
        });
    }

    public function reject(Prescription $prescription, User $reviewer, string $reason): Prescription
    {
        return DB::transaction(function () use ($prescription, $reviewer, $reason): Prescription {
            $locked = $this->lockedPrescription($prescription);
            $order = $locked->order;

            $this->ensurePending($locked);
            $this->ensureReviewableOrder($order);

            $this->inventoryService->restoreOrderStock($order);

            $locked->update([
                'status' => Prescription::STATUS_REJECTED,
                'pharmacist_id' => $reviewer->id,
                'pharmacist_notes' => $reason,
                'reviewed_at' => now(),
            ]);

            $order->update(['status' => Order::STATUS_REJECTED]);

            return $locked->fresh()->load($this->relations());
        });
    }

    private function lockedPrescription(Prescription $prescription): Prescription
    {
        return Prescription::query()
            ->whereKey($prescription->id)
            ->lockForUpdate()
            ->firstOrFail()
            ->load(['order']);
    }

    private function ensurePending(Prescription $prescription): void
    {
        if ($prescription->status !== Prescription::STATUS_PENDING) {
            throw new InvalidArgumentException('Resep yang sudah final tidak bisa diproses ulang.');
        }
    }

    private function ensureReviewableOrder(Order $order): void
    {
        if ($order->normalizedStatus() !== Order::STATUS_WAITING_PRESCRIPTION_REVIEW) {
            throw new InvalidArgumentException('Status pesanan tidak sedang menunggu verifikasi resep.');
        }
    }

    /**
     * @return array<int, string>
     */
    private function relations(): array
    {
        return [
            'user:id,name,email,role',
            'pharmacist:id,name,email,role',
            'order.user:id,name,email,role',
            'order.items.medicine.category',
            'order.items.batchUsages.medicineBatch',
        ];
    }
}
