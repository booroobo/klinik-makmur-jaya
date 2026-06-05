<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CategoryController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(): JsonResponse
    {
        $orderedNames = [
            'Semua Kategori',
            'Obat Flu & Batuk',
            'Obat Demam & Nyeri',
            'Obat Sakit Kepala',
            'Obat Pencernaan',
            'Obat Alergi',
            'Obat Asma & Pernapasan',
            'Obat Kulit',
            'Obat Mata & Telinga',
            'Antibiotik',
            'Antidiabetes',
            'Antihipertensi',
            'Vitamin & Suplemen',
            'Jamu / Herbal',
            'Obat Anak',
            'Obat Cacing',
        ];

        return response()->json([
            'data' => Category::all()
                ->sortBy(function (Category $category) use ($orderedNames): int {
                    $index = array_search($category->name, $orderedNames, true);

                    return $index === false ? 1000 + $category->id : $index;
                })
                ->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $category = Category::create($this->validated($request));

        $this->auditLogger->log($request, 'create', 'category', "Kategori {$category->name} dibuat.", [
            'category_id' => $category->id,
            'after' => $category->only(['name', 'description']),
        ]);

        return response()->json([
            'message' => 'Kategori berhasil dibuat.',
            'data' => $category,
        ], Response::HTTP_CREATED);
    }

    public function show(Category $category): JsonResponse
    {
        return response()->json([
            'data' => $category->loadCount('medicines'),
        ]);
    }

    public function update(Request $request, Category $category): JsonResponse
    {
        $before = $category->only(['name', 'description']);
        $category->update($this->validated($request));

        $this->auditLogger->log($request, 'update', 'category', "Kategori {$category->name} diperbarui.", [
            'category_id' => $category->id,
            'before' => $before,
            'after' => $category->only(['name', 'description']),
        ]);

        return response()->json([
            'message' => 'Kategori berhasil diperbarui.',
            'data' => $category,
        ]);
    }

    public function destroy(Request $request, Category $category): JsonResponse
    {
        if ($category->medicines()->exists()) {
            return response()->json([
                'message' => 'Kategori tidak bisa dihapus karena masih digunakan oleh obat.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $category->delete();

        $this->auditLogger->log($request, 'delete', 'category', "Kategori {$category->name} dihapus.", [
            'category_id' => $category->id,
            'name' => $category->name,
        ]);

        return response()->json([
            'message' => 'Kategori berhasil dihapus.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);
    }
}
