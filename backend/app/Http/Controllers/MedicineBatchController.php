<?php

namespace App\Http\Controllers;

use App\Models\MedicineBatch;
use App\Models\Medicine;
use App\Models\MedicineVariant;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class MedicineBatchController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(Request $request): JsonResponse
    {
        $query = MedicineBatch::query()
            ->with(['medicine.category', 'variant'])
            ->when($request->filled('medicine_id'), fn ($query) => $query->where('medicine_id', $request->integer('medicine_id')))
            ->orderBy('expired_date');

        return response()->json([
            'data' => $query->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $batch = MedicineBatch::create($this->validated($request, true))->load(['medicine', 'variant']);

        $this->auditLogger->log($request, 'create', 'medicine_batch', "Batch {$batch->batch_number} dibuat untuk {$batch->medicine?->name}.", [
            'medicine_batch_id' => $batch->id,
            'medicine_id' => $batch->medicine_id,
            'after' => $batch->only(['medicine_variant_id', 'batch_number', 'expired_date', 'quantity', 'purchase_price']),
        ]);

        return response()->json([
            'message' => 'Batch obat berhasil dibuat.',
            'data' => $batch,
        ], Response::HTTP_CREATED);
    }

    public function update(Request $request, MedicineBatch $medicineBatch): JsonResponse
    {
        $before = $medicineBatch->only(['medicine_id', 'medicine_variant_id', 'batch_number', 'expired_date', 'quantity', 'purchase_price']);
        $medicineBatch->update($this->validated($request, false));
        $updatedBatch = $medicineBatch->fresh(['medicine', 'variant']);

        $this->auditLogger->log($request, 'update', 'medicine_batch', "Batch {$updatedBatch->batch_number} diperbarui.", [
            'medicine_batch_id' => $updatedBatch->id,
            'before' => $before,
            'after' => $updatedBatch->only(['medicine_id', 'medicine_variant_id', 'batch_number', 'expired_date', 'quantity', 'purchase_price']),
        ]);

        return response()->json([
            'message' => 'Batch obat berhasil diperbarui.',
            'data' => $updatedBatch,
        ]);
    }

    public function destroy(Request $request, MedicineBatch $medicineBatch): JsonResponse
    {
        $medicineBatch->delete();

        $this->auditLogger->log($request, 'delete', 'medicine_batch', "Batch {$medicineBatch->batch_number} dihapus.", [
            'medicine_batch_id' => $medicineBatch->id,
            'medicine_id' => $medicineBatch->medicine_id,
        ]);

        return response()->json([
            'message' => 'Batch obat berhasil dihapus.',
        ]);
    }

    public function restore(Request $request, int $id): JsonResponse
    {
        $medicineBatch = MedicineBatch::withTrashed()->findOrFail($id);
        $medicineBatch->restore();
        $restoredBatch = $medicineBatch->fresh(['medicine', 'variant']);

        $this->auditLogger->log($request, 'restore', 'medicine_batch', "Batch {$restoredBatch->batch_number} dikembalikan.", [
            'medicine_batch_id' => $restoredBatch->id,
            'medicine_id' => $restoredBatch->medicine_id,
        ]);

        return response()->json([
            'message' => 'Batch obat berhasil dikembalikan.',
            'data' => $restoredBatch,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $requireMedicine): array
    {
        $data = $request->validate([
            'medicine_id' => [$requireMedicine ? 'required' : 'sometimes', 'integer', Rule::exists('medicines', 'id')],
            'medicine_variant_id' => ['nullable', 'integer', Rule::exists('medicine_variants', 'id')->whereNull('deleted_at')],
            'batch_number' => ['required', 'string', 'max:255'],
            'expired_date' => ['required', 'date', 'after_or_equal:today'],
            'quantity' => ['required', 'integer', 'min:0'],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
        ]);

        $medicineId = $data['medicine_id'] ?? $request->route('medicineBatch')?->medicine_id;
        $medicine = Medicine::findOrFail($medicineId);
        $variantId = $data['medicine_variant_id'] ?? null;

        if ($medicine->has_variants && ! $variantId) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'medicine_variant_id' => ['Varian wajib dipilih untuk obat yang memiliki varian.'],
            ]);
        }

        if (! $medicine->has_variants) {
            $data['medicine_variant_id'] = null;

            return $data;
        }

        $variantBelongsToMedicine = MedicineVariant::query()
            ->whereKey($variantId)
            ->where('medicine_id', $medicine->id)
            ->where('is_active', true)
            ->exists();

        if (! $variantBelongsToMedicine) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'medicine_variant_id' => ['Varian tidak valid untuk obat yang dipilih.'],
            ]);
        }

        return $data;
    }
}
