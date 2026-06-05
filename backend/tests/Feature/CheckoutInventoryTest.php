<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\Category;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\Order;
use App\Models\OrderItemBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CheckoutInventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_deducts_stock_from_nearest_expiry_batch_first(): void
    {
        [$user, $medicine] = $this->createCustomerAndMedicine();
        $nearest = $this->createBatch($medicine, 'NEAR', now()->addMonth()->toDateString(), 3, 5000);
        $later = $this->createBatch($medicine, 'LATER', now()->addMonths(6)->toDateString(), 5, 6000);
        $this->putInCart($user, $medicine, 4);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/checkout', $this->checkoutPayload());

        $response->assertCreated();
        $this->assertSame(0, $nearest->fresh()->quantity);
        $this->assertSame(4, $later->fresh()->quantity);

        $order = Order::firstOrFail();
        $orderItem = $order->items()->firstOrFail();

        $nearestUsage = OrderItemBatch::where('order_item_id', $orderItem->id)
            ->where('medicine_batch_id', $nearest->id)
            ->firstOrFail();
        $laterUsage = OrderItemBatch::where('order_item_id', $orderItem->id)
            ->where('medicine_batch_id', $later->id)
            ->firstOrFail();

        $this->assertSame(3, $nearestUsage->quantity);
        $this->assertSame('5000.00', $nearestUsage->unit_cost);
        $this->assertSame($nearest->expired_date->toDateString(), $nearestUsage->expiry_date->toDateString());
        $this->assertSame(1, $laterUsage->quantity);
        $this->assertSame('6000.00', $laterUsage->unit_cost);
        $this->assertSame($later->expired_date->toDateString(), $laterUsage->expiry_date->toDateString());
        $this->assertSame(4, OrderItemBatch::where('order_item_id', $orderItem->id)->sum('quantity'));
    }

    public function test_checkout_fails_when_active_unexpired_stock_is_insufficient_and_keeps_cart(): void
    {
        [$user, $medicine] = $this->createCustomerAndMedicine();
        $batch = $this->createBatch($medicine, 'LIMITED', now()->addMonth()->toDateString(), 2);
        $cart = $this->putInCart($user, $medicine, 3);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/checkout', $this->checkoutPayload());

        $response->assertUnprocessable()
            ->assertJsonPath('message', "Stok {$medicine->name} tidak mencukupi.");
        $this->assertSame(2, $batch->fresh()->quantity);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseHas('cart_items', [
            'cart_id' => $cart->id,
            'medicine_id' => $medicine->id,
            'quantity' => 3,
        ]);
    }

    public function test_checkout_does_not_use_expired_or_soft_deleted_batches(): void
    {
        [$user, $medicine] = $this->createCustomerAndMedicine();
        $expired = $this->createBatch($medicine, 'EXPIRED', now()->subDay()->toDateString(), 10);
        $deleted = $this->createBatch($medicine, 'DELETED', now()->addMonth()->toDateString(), 10);
        $deleted->delete();
        $active = $this->createBatch($medicine, 'ACTIVE', now()->addMonths(2)->toDateString(), 2);
        $this->putInCart($user, $medicine, 3);

        Sanctum::actingAs($user);

        $this->postJson('/api/checkout', $this->checkoutPayload())->assertUnprocessable();

        $this->assertSame(10, $expired->fresh()->quantity);
        $this->assertSame(10, MedicineBatch::withTrashed()->findOrFail($deleted->id)->quantity);
        $this->assertSame(2, $active->fresh()->quantity);
        $this->assertDatabaseCount('order_item_batches', 0);
    }

    public function test_batch_stock_never_becomes_negative(): void
    {
        [$user, $medicine] = $this->createCustomerAndMedicine();
        $batch = $this->createBatch($medicine, 'ONLY', now()->addMonth()->toDateString(), 1);
        $this->putInCart($user, $medicine, 2);

        Sanctum::actingAs($user);

        $this->postJson('/api/checkout', $this->checkoutPayload())->assertUnprocessable();

        $this->assertSame(1, $batch->fresh()->quantity);
        $this->assertDatabaseMissing('medicine_batches', [
            'id' => $batch->id,
            'quantity' => -1,
        ]);
    }

    public function test_two_sequential_checkouts_cannot_consume_more_than_available_stock(): void
    {
        [$firstUser, $medicine] = $this->createCustomerAndMedicine();
        $secondUser = User::factory()->create(['role' => User::ROLE_PELANGGAN]);
        $batch = $this->createBatch($medicine, 'SHARED', now()->addMonth()->toDateString(), 3);
        $this->putInCart($firstUser, $medicine, 2);
        $secondCart = $this->putInCart($secondUser, $medicine, 2);

        Sanctum::actingAs($firstUser);
        $this->postJson('/api/checkout', $this->checkoutPayload())->assertCreated();

        Sanctum::actingAs($secondUser);
        $this->postJson('/api/checkout', $this->checkoutPayload())->assertUnprocessable();

        $this->assertSame(1, $batch->fresh()->quantity);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseHas('cart_items', [
            'cart_id' => $secondCart->id,
            'quantity' => 2,
        ]);
    }

    /**
     * @return array{0: User, 1: Medicine}
     */
    private function createCustomerAndMedicine(): array
    {
        $user = User::factory()->create(['role' => User::ROLE_PELANGGAN]);
        $category = Category::create([
            'name' => 'Kategori Checkout',
            'description' => 'Kategori pengujian checkout.',
        ]);
        $medicine = Medicine::create([
            'category_id' => $category->id,
            'name' => 'Obat FIFO',
            'price' => 10000,
            'minimum_stock' => 0,
            'requires_prescription' => false,
            'is_active' => true,
        ]);

        return [$user, $medicine];
    }

    private function createBatch(
        Medicine $medicine,
        string $number,
        string $expiryDate,
        int $quantity,
        float $purchasePrice = 4000,
    ): MedicineBatch {
        return MedicineBatch::create([
            'medicine_id' => $medicine->id,
            'batch_number' => $number,
            'expired_date' => $expiryDate,
            'quantity' => $quantity,
            'purchase_price' => $purchasePrice,
        ]);
    }

    private function putInCart(User $user, Medicine $medicine, int $quantity): Cart
    {
        $cart = Cart::create(['user_id' => $user->id]);
        $cart->items()->create([
            'medicine_id' => $medicine->id,
            'quantity' => $quantity,
        ]);

        return $cart;
    }

    /**
     * @return array<string, string>
     */
    private function checkoutPayload(): array
    {
        return [
            'fulfillment_method' => 'pickup',
            'payment_method' => 'cashier',
            'customer_name' => 'Pelanggan Test',
            'customer_phone' => '08123456789',
        ];
    }
}
