<?php

namespace App\Http\Controllers;

use App\Models\Medicine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CatalogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Medicine::query()
            ->with(['category', 'supplier'])
            ->withSum('batches as total_stock_sum', 'quantity')
            ->where('is_active', true)
            ->when($request->filled('search'), fn ($query) => $query->where('name', 'ilike', '%'.$request->string('search')->toString().'%'))
            ->when($request->filled('category_id'), fn ($query) => $query->where('category_id', $request->integer('category_id')))
            ->when($request->filled('requires_prescription'), fn ($query) => $query->where('requires_prescription', $request->boolean('requires_prescription')));

        if (in_array($request->string('sort_price')->toString(), ['asc', 'desc'], true)) {
            $query->orderBy('price', $request->string('sort_price')->toString());
        } else {
            $query->orderBy('name');
        }

        return response()->json(
            $query->paginate($request->integer('per_page', 12))->withQueryString(),
        );
    }

    public function autocomplete(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'min:1', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:20'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'requires_prescription' => ['nullable', 'boolean'],
        ]);
        $term = mb_strtolower($data['q']);
        $limit = $data['limit'] ?? 8;
        $driver = DB::connection()->getDriverName();

        $query = Medicine::query()
            ->with('category:id,name')
            ->where('is_active', true)
            ->when(isset($data['category_id']), fn ($query) => $query->where('category_id', $data['category_id']))
            ->when($request->filled('requires_prescription'), fn ($query) => $query->where('requires_prescription', $request->boolean('requires_prescription')));

        if ($driver === 'pgsql') {
            $query->where('name', 'ILIKE', '%'.$data['q'].'%')
                ->orderByRaw('CASE WHEN name ILIKE ? THEN 0 ELSE 1 END', [$data['q'].'%']);
        } else {
            $query->whereRaw('lower(name) like ?', ['%'.$term.'%'])
                ->orderByRaw('CASE WHEN lower(name) like ? THEN 0 ELSE 1 END', [$term.'%']);
        }

        $medicines = $query
            ->orderBy('name')
            ->limit($limit)
            ->get();

        if ($medicines->isEmpty()) {
            $baseQuery = Medicine::query()
                ->with('category:id,name')
                ->where('is_active', true)
                ->when(isset($data['category_id']), fn ($query) => $query->where('category_id', $data['category_id']))
                ->when($request->filled('requires_prescription'), fn ($query) => $query->where('requires_prescription', $request->boolean('requires_prescription')));

            $threshold = max(2, (int) floor(mb_strlen($term) * 0.35));
            $medicines = $baseQuery->get()
                ->map(fn (Medicine $medicine) => [
                    'medicine' => $medicine,
                    'distance' => levenshtein($term, mb_strtolower($medicine->name)),
                ])
                ->filter(fn (array $item): bool => $item['distance'] <= $threshold)
                ->sortBy('distance')
                ->take($limit)
                ->pluck('medicine')
                ->values();
        }

        $medicines = $medicines->map(fn (Medicine $medicine): array => [
            'id' => $medicine->id,
            'name' => $medicine->name,
            'category' => $medicine->category?->name,
            'price' => (float) $medicine->price,
        ]);

        return response()->json(['data' => $medicines]);
    }

    public function show(int $id): JsonResponse
    {
        $medicine = Medicine::query()
            ->with([
                'category',
                'supplier',
                'batches' => fn ($query) => $query
                    ->whereDate('expired_date', '>=', now()->toDateString())
                    ->orderBy('expired_date'),
            ])
            ->where('is_active', true)
            ->findOrFail($id);

        return response()->json([
            'data' => $medicine,
        ]);
    }
}
