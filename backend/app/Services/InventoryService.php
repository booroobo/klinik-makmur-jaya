<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\MedicineVariant;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemBatch;
use Illuminate\Database\Eloquent\Collection;

class InventoryService
{
    public function availableStock(Medicine|int $medicine, MedicineVariant|int|null $variant = null): int
    {
        $medicineId = $medicine instanceof Medicine ? $medicine->id : $medicine;
        $variantId = $variant instanceof MedicineVariant ? $variant->id : $variant;

        return (int) MedicineBatch::query()
            ->where('medicine_id', $medicineId)
            ->when($variantId !== null, fn ($query) => $query->where('medicine_variant_id', $variantId))
            ->when($variantId === null, fn ($query) => $query->whereNull('medicine_variant_id'))
            ->whereDate('expired_date', '>=', now()->toDateString())
            ->where('quantity', '>', 0)
            ->sum('quantity');
    }

    /**
     * @return array<int, array{batch: MedicineBatch, quantity: int}>
     */
    public function prepareFifoAllocation(Medicine $medicine, int $quantity, ?MedicineVariant $variant = null): array
    {
        $batches = $this->lockAvailableBatches($medicine->id, $variant?->id);
        $availableStock = (int) $batches->sum('quantity');

        if ($availableStock < $quantity) {
            throw new InsufficientStockException(
                medicineId: $medicine->id,
                medicineName: $medicine->name,
                requestedQuantity: $quantity,
                availableStock: $availableStock,
            );
        }

        $remaining = $quantity;
        $allocations = [];

        foreach ($batches as $batch) {
            if ($remaining === 0) {
                break;
            }

            $deductedQuantity = min($remaining, $batch->quantity);
            $allocations[] = [
                'batch' => $batch,
                'quantity' => $deductedQuantity,
            ];

            $remaining -= $deductedQuantity;
        }

        return $allocations;
    }

    /**
     * @param array<int, array{batch: MedicineBatch, quantity: int}> $allocations
     */
    public function applyFifoAllocation(OrderItem $orderItem, array $allocations): void
    {
        foreach ($allocations as $allocation) {
            $batch = $allocation['batch'];
            $deductedQuantity = $allocation['quantity'];

            $batch->decrement('quantity', $deductedQuantity);

            $orderItem->batchUsages()->create([
                'medicine_batch_id' => $batch->id,
                'quantity' => $deductedQuantity,
                'unit_cost' => $batch->purchase_price,
                'expiry_date' => $batch->expired_date?->toDateString(),
            ]);
        }
    }

    public function restoreOrderStock(Order $order): void
    {
        $usages = OrderItemBatch::query()
            ->whereHas('orderItem', fn ($query) => $query->where('order_id', $order->id))
            ->get()
            ->groupBy('medicine_batch_id');

        foreach ($usages as $batchId => $batchUsages) {
            $quantity = (int) $batchUsages->sum('quantity');

            if ($quantity <= 0) {
                continue;
            }

            $batch = MedicineBatch::withTrashed()
                ->whereKey($batchId)
                ->lockForUpdate()
                ->first();

            if ($batch) {
                $batch->increment('quantity', $quantity);
            }
        }
    }

    /**
     * @return Collection<int, MedicineBatch>
     */
    private function lockAvailableBatches(int $medicineId, ?int $variantId = null): Collection
    {
        return MedicineBatch::query()
            ->where('medicine_id', $medicineId)
            ->when($variantId !== null, fn ($query) => $query->where('medicine_variant_id', $variantId))
            ->when($variantId === null, fn ($query) => $query->whereNull('medicine_variant_id'))
            ->whereDate('expired_date', '>=', now()->toDateString())
            ->where('quantity', '>', 0)
            ->orderBy('expired_date')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }
}
