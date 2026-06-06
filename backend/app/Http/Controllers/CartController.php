<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Medicine;
use App\Models\MedicineVariant;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class CartController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->cartPayload($this->cartForUser($request)),
        ]);
    }

    public function storeItem(Request $request): JsonResponse
    {
        $data = $request->validate([
            'medicine_id' => ['required', 'integer', Rule::exists('medicines', 'id')],
            'medicine_variant_id' => ['nullable', 'integer'],
            'quantity' => ['nullable', 'integer', 'min:1'],
        ]);

        $cart = $this->cartForUser($request);
        $medicine = Medicine::where('is_active', true)->findOrFail($data['medicine_id']);
        $variant = $this->resolveVariant($medicine, $data['medicine_variant_id'] ?? null);
        $quantity = $data['quantity'] ?? 1;
        $item = $cart->items()->firstOrNew([
            'medicine_id' => $medicine->id,
            'medicine_variant_id' => $variant?->id,
        ]);
        $nextQuantity = ($item->exists ? $item->quantity : 0) + $quantity;

        $this->ensureStockAvailable($medicine, $variant, $nextQuantity);

        $item->quantity = $nextQuantity;
        $item->save();

        $this->auditLogger->success($request, 'add_item', 'cart', 'Item ditambahkan ke keranjang.', [
            'medicine_id' => $medicine->id,
            'medicine_variant_id' => $variant?->id,
            'quantity' => $quantity,
        ]);

        return response()->json([
            'message' => 'Obat berhasil ditambahkan ke keranjang.',
            'data' => $this->cartPayload($cart),
        ], Response::HTTP_CREATED);
    }

    public function updateItem(Request $request, CartItem $cartItem): JsonResponse
    {
        $this->ensureOwnItem($request, $cartItem);

        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
            'medicine_variant_id' => ['sometimes', 'nullable', 'integer'],
        ]);

        $medicine = $cartItem->medicine;
        $variantId = array_key_exists('medicine_variant_id', $data)
            ? $data['medicine_variant_id']
            : $cartItem->medicine_variant_id;
        $variant = $this->resolveVariant($medicine, $variantId);
        $targetItem = $cartItem->cart->items()
            ->where('medicine_id', $medicine->id)
            ->where('id', '!=', $cartItem->id)
            ->when($variant, fn ($query) => $query->where('medicine_variant_id', $variant->id))
            ->when(! $variant, fn ($query) => $query->whereNull('medicine_variant_id'))
            ->first();
        $nextQuantity = $data['quantity'] + ($targetItem?->quantity ?? 0);

        $this->ensureStockAvailable($medicine, $variant, $nextQuantity);

        if ($targetItem) {
            $targetItem->update(['quantity' => $nextQuantity]);
            $cartItem->delete();
        } else {
            $cartItem->update([
                'medicine_variant_id' => $variant?->id,
                'quantity' => $data['quantity'],
            ]);
        }

        $this->auditLogger->success($request, 'update_item', 'cart', 'Item keranjang diperbarui.', [
            'cart_item_id' => $cartItem->id,
            'medicine_id' => $medicine->id,
            'medicine_variant_id' => $variant?->id,
            'quantity' => $nextQuantity,
        ]);

        return response()->json([
            'message' => 'Jumlah item keranjang berhasil diperbarui.',
            'data' => $this->cartPayload($cartItem->cart),
        ]);
    }

    public function destroyItem(Request $request, CartItem $cartItem): JsonResponse
    {
        $this->ensureOwnItem($request, $cartItem);
        $cart = $cartItem->cart;
        $cartItem->delete();

        return response()->json([
            'message' => 'Item keranjang berhasil dihapus.',
            'data' => $this->cartPayload($cart),
        ]);
    }

    public function clear(Request $request): JsonResponse
    {
        $cart = $this->cartForUser($request);
        $cart->items()->delete();

        return response()->json([
            'message' => 'Keranjang berhasil dikosongkan.',
            'data' => $this->cartPayload($cart),
        ]);
    }

    private function cartForUser(Request $request): Cart
    {
        return Cart::firstOrCreate([
            'user_id' => $request->user()->id,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function cartPayload(Cart $cart): array
    {
        $cart->load([
            'items.medicine.category',
            'items.medicine.supplier',
            'items.medicine.batches',
            'items.medicine.variants.batches',
            'items.variant.batches',
        ]);

        return [
            'id' => $cart->id,
            'items' => $cart->items,
            'subtotal' => (float) $cart->items->sum(fn (CartItem $item) => $item->line_total),
            'total_quantity' => (int) $cart->items->sum('quantity'),
            'has_prescription_items' => $cart->items->contains(fn (CartItem $item) => (bool) $item->medicine?->requires_prescription),
        ];
    }

    private function ensureStockAvailable(Medicine $medicine, ?MedicineVariant $variant, int $quantity): void
    {
        $availableStock = $variant?->stock ?? $medicine->total_stock;

        if ($quantity > $availableStock) {
            throw ValidationException::withMessages([
                'quantity' => [$variant ? 'Jumlah melebihi stok tersedia untuk varian yang dipilih.' : 'Jumlah melebihi stok tersedia.'],
            ]);
        }
    }

    private function resolveVariant(Medicine $medicine, mixed $variantId): ?MedicineVariant
    {
        if (! $medicine->has_variants) {
            if ($variantId !== null && $variantId !== '') {
                throw ValidationException::withMessages([
                    'medicine_variant_id' => ['Obat ini tidak memiliki varian.'],
                ]);
            }

            return null;
        }

        if ($variantId === null || $variantId === '') {
            throw ValidationException::withMessages([
                'medicine_variant_id' => ['Varian wajib dipilih.'],
            ]);
        }

        $variant = $medicine->variants()
            ->with('batches')
            ->whereKey($variantId)
            ->where('is_active', true)
            ->first();

        if (! $variant) {
            throw ValidationException::withMessages([
                'medicine_variant_id' => ['Varian tidak valid atau sudah tidak aktif.'],
            ]);
        }

        return $variant;
    }

    private function ensureOwnItem(Request $request, CartItem $cartItem): void
    {
        if ($cartItem->cart->user_id !== $request->user()->id) {
            abort(Response::HTTP_NOT_FOUND);
        }
    }
}
