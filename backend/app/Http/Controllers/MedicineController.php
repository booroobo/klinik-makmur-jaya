<?php

namespace App\Http\Controllers;

use App\Models\Medicine;
use App\Models\MedicineVariant;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class MedicineController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Medicine::query()
            ->with([
                'category',
                'supplier',
                'variants.batches',
                'batches' => fn ($query) => $query->with('variant')->orderBy('expired_date'),
            ])
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
        $variants = $data['variants'] ?? [];
        unset($data['variants']);
        $this->normalizeMedicinePrice($data, $variants);
        $data['image'] = $this->storeImage($request);

        $medicine = DB::transaction(function () use ($data, $variants): Medicine {
            $medicine = Medicine::create($data);
            $this->syncVariants($medicine, $variants);

            return $medicine;
        })->load(['category', 'supplier', 'variants.batches', 'batches.variant']);

        $this->auditLogger->log($request, 'create', 'medicine', "Obat {$medicine->name} dibuat.", [
            'medicine_id' => $medicine->id,
            'after' => $medicine->only(['category_id', 'supplier_id', 'name', 'price', 'has_variants', 'minimum_stock', 'requires_prescription', 'is_active']),
            'variants' => $medicine->variants->map->only(['id', 'name', 'price', 'sku', 'is_active', 'sort_order'])->all(),
        ]);

        return response()->json([
            'message' => 'Obat berhasil dibuat.',
            'data' => $medicine,
        ], Response::HTTP_CREATED);
    }

    public function show(Medicine $medicine): JsonResponse
    {
        return response()->json([
            'data' => $medicine->load([
                'category',
                'supplier',
                'variants.batches',
                'batches' => fn ($query) => $query->with('variant')->orderBy('expired_date'),
            ]),
        ]);
    }

    public function update(Request $request, Medicine $medicine): JsonResponse
    {
        $before = $medicine->load('variants')->only(['category_id', 'supplier_id', 'name', 'price', 'has_variants', 'minimum_stock', 'requires_prescription', 'is_active', 'image']);
        $before['variants'] = $medicine->variants->map->only(['id', 'name', 'price', 'sku', 'is_active', 'sort_order'])->all();
        $data = $this->validated($request, $medicine);
        $variants = $data['variants'] ?? [];
        unset($data['variants']);
        $this->normalizeMedicinePrice($data, $variants);
        $image = $this->storeImage($request, $medicine);

        if ($image !== null) {
            $data['image'] = $image;
        }

        DB::transaction(function () use ($medicine, $data, $variants): void {
            $medicine->update($data);
            $this->syncVariants($medicine, $variants);
        });
        $updatedMedicine = $medicine->fresh(['category', 'supplier', 'variants.batches', 'batches.variant']);

        $this->auditLogger->log($request, 'update', 'medicine', "Obat {$updatedMedicine->name} diperbarui.", [
            'medicine_id' => $updatedMedicine->id,
            'before' => $before,
            'after' => $updatedMedicine->only(['category_id', 'supplier_id', 'name', 'price', 'has_variants', 'minimum_stock', 'requires_prescription', 'is_active', 'image']),
            'variants' => $updatedMedicine->variants->map->only(['id', 'name', 'price', 'sku', 'is_active', 'sort_order'])->all(),
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
        $restoredMedicine = $medicine->fresh(['category', 'supplier', 'variants.batches', 'batches.variant']);

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
            'has_variants' => ['sometimes', 'boolean'],
            'price' => ['required_unless:has_variants,true', 'nullable', 'numeric', 'min:0'],
            'variants' => ['exclude_unless:has_variants,true', 'required_if:has_variants,true', 'array', 'min:1'],
            'variants.*.id' => ['nullable', 'integer'],
            'variants.*.name' => ['required_if:has_variants,true', 'string', 'max:100'],
            'variants.*.price' => ['required_if:has_variants,true', 'numeric', 'gt:0'],
            'variants.*.sku' => ['nullable', 'string', 'max:100'],
            'variants.*.is_active' => ['sometimes', 'boolean'],
            'variants.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'minimum_stock' => ['nullable', 'integer', 'min:0'],
            'requires_prescription' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:2048'],
        ]);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int, array<string, mixed>> $variants
     */
    private function normalizeMedicinePrice(array &$data, array $variants): void
    {
        $data['has_variants'] = (bool) ($data['has_variants'] ?? false);

        if ($data['has_variants']) {
            $data['price'] = collect($variants)->min(fn (array $variant): float => (float) ($variant['price'] ?? 0));
        }
    }

    /**
     * @param array<int, array<string, mixed>> $variants
     */
    private function syncVariants(Medicine $medicine, array $variants): void
    {
        if (! $medicine->has_variants) {
            $medicine->variants()->update(['is_active' => false]);
            $medicine->variants()->delete();
            $medicine->batches()->update(['medicine_variant_id' => null]);

            return;
        }

        $normalizedNames = collect($variants)
            ->map(fn (array $variant): string => mb_strtolower(trim((string) ($variant['name'] ?? ''))));

        if ($normalizedNames->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages([
                'variants' => ['Nama varian tidak boleh duplikat dalam satu obat.'],
            ]);
        }

        $keptIds = [];

        foreach ($variants as $index => $variantData) {
            $variant = isset($variantData['id'])
                ? MedicineVariant::withTrashed()
                    ->where('medicine_id', $medicine->id)
                    ->findOrFail($variantData['id'])
                : new MedicineVariant(['medicine_id' => $medicine->id]);

            if ($variant->trashed()) {
                $variant->restore();
            }

            $variant->fill([
                'name' => trim($variantData['name']),
                'price' => $variantData['price'],
                'sku' => filled($variantData['sku'] ?? null) ? trim($variantData['sku']) : null,
                'is_active' => $variantData['is_active'] ?? true,
                'sort_order' => $variantData['sort_order'] ?? $index,
            ])->save();
            $keptIds[] = $variant->id;
        }

        $removedVariants = $medicine->variants()->whereNotIn('id', $keptIds)->get();

        foreach ($removedVariants as $variant) {
            $variant->update(['is_active' => false]);
            $variant->delete();
        }
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
