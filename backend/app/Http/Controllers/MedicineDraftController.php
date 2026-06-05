<?php

namespace App\Http\Controllers;

use App\Models\MedicineDraft;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class MedicineDraftController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(Request $request): JsonResponse
    {
        $drafts = MedicineDraft::query()
            ->where('user_id', $request->user()->id)
            ->where('expires_at', '>', now())
            ->latest()
            ->get();

        return response()->json([
            'data' => $drafts,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $draft = MedicineDraft::create([
            'user_id' => $request->user()->id,
            'payload' => $this->payloadFromRequest($request),
            'image_path' => $this->storeImage($request),
            'expires_at' => now()->addDays(7),
        ]);

        $this->auditLogger->log($request, 'create', 'medicine_draft', 'Draft obat dibuat.', [
            'medicine_draft_id' => $draft->id,
            'name' => $draft->payload['name'] ?? null,
            'expires_at' => $draft->expires_at,
        ]);

        return response()->json([
            'message' => 'Draft obat berhasil disimpan.',
            'data' => $draft,
        ], Response::HTTP_CREATED);
    }

    public function show(Request $request, MedicineDraft $medicineDraft): JsonResponse
    {
        $this->ensureOwnedActive($request, $medicineDraft);

        return response()->json([
            'data' => $medicineDraft,
        ]);
    }

    public function update(Request $request, MedicineDraft $medicineDraft): JsonResponse
    {
        $this->ensureOwnedActive($request, $medicineDraft);
        $this->validated($request);
        $before = [
            'name' => $medicineDraft->payload['name'] ?? null,
            'image_path' => $medicineDraft->image_path,
            'expires_at' => $medicineDraft->expires_at,
        ];

        $imagePath = $medicineDraft->image_path;
        if ($request->hasFile('image')) {
            $medicineDraft->deleteDraftImage();
            $imagePath = $this->storeImage($request);
        }

        $medicineDraft->update([
            'payload' => $this->payloadFromRequest($request),
            'image_path' => $imagePath,
            'expires_at' => now()->addDays(7),
        ]);
        $updatedDraft = $medicineDraft->fresh();

        $this->auditLogger->log($request, 'update', 'medicine_draft', 'Draft obat diperbarui.', [
            'medicine_draft_id' => $updatedDraft->id,
            'before' => $before,
            'after' => [
                'name' => $updatedDraft->payload['name'] ?? null,
                'image_path' => $updatedDraft->image_path,
                'expires_at' => $updatedDraft->expires_at,
            ],
        ]);

        return response()->json([
            'message' => 'Draft obat berhasil diperbarui.',
            'data' => $updatedDraft,
        ]);
    }

    public function destroy(Request $request, MedicineDraft $medicineDraft): JsonResponse
    {
        if ($medicineDraft->user_id !== $request->user()->id) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $medicineDraft->deleteDraftImage();
        $medicineDraft->delete();

        $this->auditLogger->log($request, 'delete', 'medicine_draft', 'Draft obat dihapus.', [
            'medicine_draft_id' => $medicineDraft->id,
            'name' => $medicineDraft->payload['name'] ?? null,
        ]);

        return response()->json([
            'message' => 'Draft obat berhasil dihapus.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'payload' => ['nullable'],
            'name' => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer'],
            'supplier_id' => ['nullable', 'integer'],
            'description' => ['nullable', 'string'],
            'composition' => ['nullable', 'string'],
            'dosage' => ['nullable', 'string', 'max:255'],
            'side_effects' => ['nullable', 'string'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'minimum_stock' => ['nullable', 'integer', 'min:0'],
            'requires_prescription' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:2048'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadFromRequest(Request $request): array
    {
        if ($request->filled('payload')) {
            $payload = $request->input('payload');

            if (is_string($payload)) {
                return json_decode($payload, true) ?: [];
            }

            return is_array($payload) ? $payload : [];
        }

        return $request->only([
            'name',
            'category_id',
            'supplier_id',
            'description',
            'composition',
            'dosage',
            'side_effects',
            'price',
            'minimum_stock',
            'requires_prescription',
            'is_active',
        ]);
    }

    private function storeImage(Request $request): ?string
    {
        if (! $request->hasFile('image') || ! $request->file('image')?->isValid()) {
            return null;
        }

        return $request->file('image')->store('medicine-drafts', 'public');
    }

    private function ensureOwnedActive(Request $request, MedicineDraft $medicineDraft): void
    {
        if ($medicineDraft->user_id !== $request->user()->id || $medicineDraft->expires_at->isPast()) {
            abort(Response::HTTP_NOT_FOUND);
        }
    }
}
