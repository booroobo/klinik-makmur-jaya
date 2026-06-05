<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientStockException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Services\AuditLogger;
use App\Services\InventoryService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly InventoryService $inventoryService,
        private readonly NotificationService $notificationService,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'fulfillment_method' => ['required', Rule::in(['pickup', 'delivery'])],
            'payment_method' => ['required', Rule::in(['bank_transfer', 'cashier', 'e_wallet'])],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'customer_address' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'prescription_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:4096'],
        ]);

        if ($data['fulfillment_method'] === 'delivery' && blank($data['customer_address'] ?? null)) {
            $this->auditLogger->failed($request, 'checkout', 'order', 'Delivery address required', 'Checkout gagal karena alamat pengiriman belum diisi.', httpStatus: Response::HTTP_UNPROCESSABLE_ENTITY);

            return response()->json([
                'message' => 'Alamat wajib diisi untuk pengiriman.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $cart = Cart::firstOrCreate(['user_id' => $request->user()->id]);
        $cart->load(['items.medicine.batches']);

        if ($cart->items->isEmpty()) {
            $this->auditLogger->failed($request, 'checkout', 'order', 'Cart is empty', 'Checkout gagal karena keranjang kosong.', [
                'cart_id' => $cart->id,
            ], httpStatus: Response::HTTP_UNPROCESSABLE_ENTITY);

            return response()->json([
                'message' => 'Keranjang masih kosong.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $hasPrescriptionItems = $cart->items->contains(
            fn (CartItem $item): bool => (bool) $item->medicine?->requires_prescription,
        );

        if ($hasPrescriptionItems && ! $request->hasFile('prescription_file')) {
            $this->auditLogger->failed($request, 'checkout', 'order', 'Prescription file required', 'Checkout gagal karena resep wajib belum diupload.', [
                'cart_id' => $cart->id,
                'item_count' => $cart->items->count(),
            ], httpStatus: Response::HTTP_UNPROCESSABLE_ENTITY);

            return response()->json([
                'message' => 'Upload resep wajib untuk obat resep.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        foreach ($cart->items as $item) {
            if (! $item->medicine || ! $item->medicine->is_active) {
                $this->auditLogger->failed($request, 'checkout', 'order', 'Medicine unavailable', 'Checkout gagal karena salah satu obat tidak tersedia.', [
                    'cart_id' => $cart->id,
                    'medicine_id' => $item->medicine_id,
                ], httpStatus: Response::HTTP_UNPROCESSABLE_ENTITY);

                return response()->json([
                    'message' => 'Salah satu obat di keranjang sudah tidak tersedia.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        try {
            $order = DB::transaction(function () use ($cart, $data, $hasPrescriptionItems, $request): Order {
                $allocations = [];
                $paymentStatus = Order::PAYMENT_STATUS_UNPAID;

                foreach ($cart->items as $item) {
                    $allocations[$item->id] = $this->inventoryService->prepareFifoAllocation(
                        $item->medicine,
                        $item->quantity,
                    );
                }

                $subtotal = (float) $cart->items->sum(fn (CartItem $item): float => $item->line_total);
                $serviceFee = 2500;
                $deliveryFee = $data['fulfillment_method'] === 'delivery' ? 10000 : 0;
                $status = $hasPrescriptionItems
                    ? Order::STATUS_WAITING_PRESCRIPTION_REVIEW
                    : Order::STATUS_PENDING_PAYMENT;

                $order = Order::create([
                    'user_id' => $request->user()->id,
                    'order_number' => $this->generateOrderNumber(),
                    'status' => $status,
                    'fulfillment_method' => $data['fulfillment_method'],
                    'payment_method' => $data['payment_method'],
                    'payment_status' => $paymentStatus,
                    'subtotal' => $subtotal,
                    'service_fee' => $serviceFee,
                    'delivery_fee' => $deliveryFee,
                    'total' => $subtotal + $serviceFee + $deliveryFee,
                    'customer_name' => $data['customer_name'],
                    'customer_phone' => $data['customer_phone'] ?? null,
                    'customer_address' => $data['customer_address'] ?? null,
                    'notes' => $data['notes'] ?? null,
                ]);

                foreach ($cart->items as $item) {
                    $orderItem = $order->items()->create([
                        'medicine_id' => $item->medicine_id,
                        'medicine_name' => $item->medicine->name,
                        'price' => $item->medicine->price,
                        'quantity' => $item->quantity,
                        'subtotal' => $item->line_total,
                        'requires_prescription' => (bool) $item->medicine->requires_prescription,
                    ]);

                    $this->inventoryService->applyFifoAllocation($orderItem, $allocations[$item->id]);
                }

                if ($hasPrescriptionItems && $request->hasFile('prescription_file')) {
                    $path = $request->file('prescription_file')->store('prescriptions', 'public');

                    $order->prescription()->create([
                        'user_id' => $request->user()->id,
                        'file_path' => $path,
                        'status' => 'pending',
                    ]);
                }

                $cart->items()->delete();

                return $order->load(['items.medicine.category', 'items.batchUsages.medicineBatch', 'prescription']);
            });
        } catch (InsufficientStockException $exception) {
            $this->auditLogger->failed($request, 'checkout', 'order', 'Insufficient stock', "Checkout gagal karena stok {$exception->medicineName} tidak mencukupi.", [
                'cart_id' => $cart->id,
                'medicine_id' => $exception->medicineId,
                'medicine_name' => $exception->medicineName,
                'requested_quantity' => $exception->requestedQuantity,
                'available_stock' => $exception->availableStock,
            ], httpStatus: Response::HTTP_UNPROCESSABLE_ENTITY);

            return response()->json([
                'message' => $exception->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->auditLogger->log($request, 'checkout', 'order', "Checkout {$order->order_number} berhasil dibuat.", [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'payment_status' => $order->payment_status,
            'fulfillment_method' => $order->fulfillment_method,
            'payment_method' => $order->payment_method,
            'total' => $order->total,
            'item_count' => $order->items->count(),
            'has_prescription' => $order->prescription !== null,
        ]);
        $this->notificationService->notifyNewOrder($order);

        if ($order->prescription) {
            $this->notificationService->notifyNewPrescription($order->prescription);
        }

        return response()->json([
            'message' => 'Checkout berhasil.',
            'order_number' => $order->order_number,
            'data' => $order,
        ], Response::HTTP_CREATED);
    }

    private function generateOrderNumber(): string
    {
        do {
            $number = 'ORD-'.now()->format('ymd').'-'.Str::upper(Str::random(6));
        } while (Order::where('order_number', $number)->exists());

        return $number;
    }
}
