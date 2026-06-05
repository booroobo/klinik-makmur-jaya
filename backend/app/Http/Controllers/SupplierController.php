<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SupplierController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Supplier::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $supplier = Supplier::create($this->validated($request));

        $this->auditLogger->log($request, 'create', 'supplier', "Supplier {$supplier->name} dibuat.", [
            'supplier_id' => $supplier->id,
            'after' => $supplier->only(['name', 'phone', 'address', 'email']),
        ]);

        return response()->json([
            'message' => 'Supplier berhasil dibuat.',
            'data' => $supplier,
        ], Response::HTTP_CREATED);
    }

    public function show(Supplier $supplier): JsonResponse
    {
        return response()->json([
            'data' => $supplier->loadCount('medicines'),
        ]);
    }

    public function update(Request $request, Supplier $supplier): JsonResponse
    {
        $before = $supplier->only(['name', 'phone', 'address', 'email']);
        $supplier->update($this->validated($request));

        $this->auditLogger->log($request, 'update', 'supplier', "Supplier {$supplier->name} diperbarui.", [
            'supplier_id' => $supplier->id,
            'before' => $before,
            'after' => $supplier->only(['name', 'phone', 'address', 'email']),
        ]);

        return response()->json([
            'message' => 'Supplier berhasil diperbarui.',
            'data' => $supplier,
        ]);
    }

    public function destroy(Request $request, Supplier $supplier): JsonResponse
    {
        $supplier->delete();

        $this->auditLogger->log($request, 'delete', 'supplier', "Supplier {$supplier->name} dihapus.", [
            'supplier_id' => $supplier->id,
            'name' => $supplier->name,
        ]);

        return response()->json([
            'message' => 'Supplier berhasil dihapus.',
            'data' => ['id' => $supplier->id],
        ]);
    }

    public function restore(Request $request, int $id): JsonResponse
    {
        $supplier = Supplier::withTrashed()->findOrFail($id);
        $supplier->restore();

        $this->auditLogger->log($request, 'restore', 'supplier', "Supplier {$supplier->name} dikembalikan.", [
            'supplier_id' => $supplier->id,
            'name' => $supplier->name,
        ]);

        return response()->json([
            'message' => 'Supplier berhasil dikembalikan.',
            'data' => $supplier->fresh(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);
    }
}
