<?php

namespace App\Http\Controllers;

use App\Models\Medicine;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class MedicineController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Medicine::query()
            ->with(['category', 'supplier', 'batches' => fn ($query) => $query->orderBy('expired_date')])
            ->when($user?->role === User::ROLE_PELANGGAN, fn ($query) => $query->where('is_active', true))
            ->when($request->filled('search'), fn ($query) => $query->where('name', 'ilike', '%'.$request->string('search')->toString().'%'))
            ->when($request->filled('category_id'), fn ($query) => $query->where('category_id', $request->integer('category_id')))
            ->when($request->filled('requires_prescription'), fn ($query) => $query->where('requires_prescription', $request->boolean('requires_prescription')));

        if (in_array($request->string('sort_price')->toString(), ['asc', 'desc'], true)) {
            $query->orderBy('price', $request->string('sort_price')->toString());
        } else {
            $query->orderBy('name');
        }

        return response()->json(
            $query->paginate($request->integer('per_page', 10))->withQueryString(),
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $data['image'] = $this->storeImage($request);

        $medicine = Medicine::create($data)->load(['category', 'supplier', 'batches']);

        $this->auditLogger->log($request, 'create', 'medicine', "Obat {$medicine->name} dibuat.", [
            'medicine_id' => $medicine->id,
            'after' => $medicine->only(['category_id', 'supplier_id', 'name', 'price', 'minimum_stock', 'requires_prescription', 'is_active']),
        ]);

        return response()->json([
            'message' => 'Obat berhasil dibuat.',
            'data' => $medicine,
        ], Response::HTTP_CREATED);
    }

    public function show(Medicine $medicine): JsonResponse
    {
        return response()->json([
            'data' => $medicine->load(['category', 'supplier', 'batches' => fn ($query) => $query->orderBy('expired_date')]),
        ]);
    }

    public function update(Request $request, Medicine $medicine): JsonResponse
    {
        $before = $medicine->only(['category_id', 'supplier_id', 'name', 'price', 'minimum_stock', 'requires_prescription', 'is_active', 'image']);
        $data = $this->validated($request, $medicine);
        $image = $this->storeImage($request, $medicine);

        if ($image !== null) {
            $data['image'] = $image;
        }

        $medicine->update($data);
        $updatedMedicine = $medicine->fresh(['category', 'supplier', 'batches']);

        $this->auditLogger->log($request, 'update', 'medicine', "Obat {$updatedMedicine->name} diperbarui.", [
            'medicine_id' => $updatedMedicine->id,
            'before' => $before,
            'after' => $updatedMedicine->only(['category_id', 'supplier_id', 'name', 'price', 'minimum_stock', 'requires_prescription', 'is_active', 'image']),
        ]);

        return response()->json([
            'message' => 'Obat berhasil diperbarui.',
            'data' => $updatedMedicine,
        ]);
    }

    public function destroy(Request $request, Medicine $medicine): JsonResponse
    {
        $metadata = ['medicine_id' => $medicine->id, 'name' => $medicine->name];
        $medicine->delete();

        $this->auditLogger->log($request, 'delete', 'medicine', "Obat {$medicine->name} dihapus.", $metadata);

        return response()->json([
            'message' => 'Obat berhasil dihapus.',
        ]);
    }

    public function restore(Request $request, int $id): JsonResponse
    {
        $medicine = Medicine::withTrashed()->findOrFail($id);
        $medicine->restore();
        $restoredMedicine = $medicine->fresh(['category', 'supplier', 'batches']);

        $this->auditLogger->log($request, 'restore', 'medicine', "Obat {$restoredMedicine->name} dikembalikan.", [
            'medicine_id' => $restoredMedicine->id,
        ]);

        return response()->json([
            'message' => 'Obat berhasil dikembalikan.',
            'data' => $restoredMedicine,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Medicine $medicine = null): array
    {
        return $request->validate([
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')],
            'supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'composition' => ['nullable', 'string'],
            'dosage' => ['nullable', 'string', 'max:255'],
            'side_effects' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'minimum_stock' => ['nullable', 'integer', 'min:0'],
            'requires_prescription' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:2048'],
        ]);
    }

    private function storeImage(Request $request, ?Medicine $medicine = null): ?string
    {
        if (! $request->hasFile('image') || ! $request->file('image')?->isValid()) {
            return null;
        }

        if ($medicine?->image) {
            Storage::disk('public')->delete($medicine->image);
        }

        return $request->file('image')->store('medicines', 'public');
    }
}
