<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Medicine;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminOrderManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_all_orders(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $order = $this->createOrder();

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/orders')
            ->assertOk()
            ->assertJsonPath('data.0.id', $order->id);
    }

    public function test_kasir_can_view_all_orders(): void
    {
        $kasir = User::factory()->create(['role' => User::ROLE_KASIR]);
        $order = $this->createOrder();

        Sanctum::actingAs($kasir);

        $this->getJson('/api/admin/orders')
            ->assertOk()
            ->assertJsonPath('data.0.id', $order->id);
    }

    public function test_pelanggan_cannot_access_admin_orders(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_PELANGGAN]));

        $this->getJson('/api/admin/orders')->assertForbidden();
    }

    public function test_admin_can_update_order_status_with_valid_transition_and_audit_is_recorded(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $order = $this->createOrder([
            'status' => Order::STATUS_PAID,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
        ]);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/orders/{$order->id}/status", [
            'status' => Order::STATUS_CONFIRMED,
        ])->assertOk()
            ->assertJsonPath('data.status', Order::STATUS_CONFIRMED);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'status' => 'success',
            'action' => 'update_status',
            'module' => 'order',
        ]);
    }

    public function test_invalid_status_transition_is_rejected(): void
    {
        $kasir = User::factory()->create(['role' => User::ROLE_KASIR]);
        $order = $this->createOrder();

        Sanctum::actingAs($kasir);

        $this->patchJson("/api/admin/orders/{$order->id}/status", [
            'status' => Order::STATUS_COMPLETED,
        ])->assertUnprocessable();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => Order::STATUS_PENDING_PAYMENT,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $kasir->id,
            'status' => 'failed',
            'action' => 'update_status',
            'module' => 'order',
        ]);
    }

    public function test_update_payment_marks_order_paid(): void
    {
        $kasir = User::factory()->create(['role' => User::ROLE_KASIR]);
        $order = $this->createOrder();

        Sanctum::actingAs($kasir);

        $this->patchJson("/api/admin/orders/{$order->id}/payment", [
            'payment_status' => Order::PAYMENT_STATUS_PAID,
        ])->assertOk()
            ->assertJsonPath('data.payment_status', Order::PAYMENT_STATUS_PAID)
            ->assertJsonPath('data.status', Order::STATUS_PAID);
    }

    public function test_admin_can_cancel_order(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $order = $this->createOrder([
            'status' => Order::STATUS_CONFIRMED,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
        ]);

        Sanctum::actingAs($admin);

        $this->postJson("/api/admin/orders/{$order->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', Order::STATUS_CANCELLED);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'status' => 'success',
            'action' => 'cancel',
            'module' => 'order',
        ]);
    }

    public function test_kasir_cannot_cancel_order(): void
    {
        $kasir = User::factory()->create(['role' => User::ROLE_KASIR]);
        $order = $this->createOrder();

        Sanctum::actingAs($kasir);

        $this->postJson("/api/admin/orders/{$order->id}/cancel")->assertForbidden();
    }

    private function createOrder(array $attributes = []): Order
    {
        $user = User::factory()->create(['role' => User::ROLE_PELANGGAN]);
        $category = Category::create(['name' => 'Obat Order', 'description' => 'Kategori order.']);
        $medicine = Medicine::create([
            'category_id' => $category->id,
            'name' => 'Obat Order',
            'price' => 15000,
            'minimum_stock' => 0,
            'requires_prescription' => false,
            'is_active' => true,
        ]);
        $order = Order::create(array_merge([
            'user_id' => $user->id,
            'order_number' => 'ORD-TEST-'.str()->upper(str()->random(6)),
            'status' => Order::STATUS_PENDING_PAYMENT,
            'fulfillment_method' => Order::FULFILLMENT_PICKUP,
            'payment_method' => 'cashier',
            'payment_status' => Order::PAYMENT_STATUS_UNPAID,
            'subtotal' => 15000,
            'service_fee' => 2500,
            'delivery_fee' => 0,
            'total' => 17500,
            'customer_name' => 'Pelanggan Order',
            'customer_phone' => '08123456789',
        ], $attributes));

        $order->items()->create([
            'medicine_id' => $medicine->id,
            'medicine_name' => $medicine->name,
            'price' => $medicine->price,
            'quantity' => 1,
            'subtotal' => $medicine->price,
            'requires_prescription' => false,
        ]);

        return $order;
    }
}
