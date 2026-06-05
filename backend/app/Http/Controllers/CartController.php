<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Medicine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class CartController extends Controller
{
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
            'quantity' => ['nullable', 'integer', 'min:1'],
        ]);

        $cart = $this->cartForUser($request);
        $medicine = Medicine::where('is_active', true)->findOrFail($data['medicine_id']);
        $quantity = $data['quantity'] ?? 1;
        $item = $cart->items()->firstOrNew(['medicine_id' => $medicine->id]);
        $nextQuantity = ($item->exists ? $item->quantity : 0) + $quantity;

        $this->ensureStockAvailable($medicine, $nextQuantity);

        $item->quantity = $nextQuantity;
        $item->save();

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
        ]);

        $this->ensureStockAvailable($cartItem->medicine, $data['quantity']);
        $cartItem->update(['quantity' => $data['quantity']]);

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
        ]);

        return [
            'id' => $cart->id,
            'items' => $cart->items,
            'subtotal' => (float) $cart->items->sum(fn (CartItem $item) => $item->line_total),
            'total_quantity' => (int) $cart->items->sum('quantity'),
            'has_prescription_items' => $cart->items->contains(fn (CartItem $item) => (bool) $item->medicine?->requires_prescription),
        ];
    }

    private function ensureStockAvailable(Medicine $medicine, int $quantity): void
    {
        if ($quantity > $medicine->total_stock) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'Jumlah melebihi stok tersedia.');
        }
    }

    private function ensureOwnItem(Request $request, CartItem $cartItem): void
    {
        if ($cartItem->cart->user_id !== $request->user()->id) {
            abort(Response::HTTP_NOT_FOUND);
        }
    }
}
