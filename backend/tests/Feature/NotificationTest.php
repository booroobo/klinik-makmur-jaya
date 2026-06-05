<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\Category;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\Notification;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_own_notifications(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_PELANGGAN]);
        Notification::create([
            'user_id' => $user->id,
            'type' => 'order_status_changed',
            'title' => 'Status berubah',
            'message' => 'Pesanan berubah.',
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Status berubah')
            ->assertJsonPath('data.0.is_read', false);
    }

    public function test_admin_can_view_role_target_notifications(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        Notification::create([
            'role_target' => User::ROLE_ADMIN,
            'type' => 'stock_critical',
            'title' => 'Stok kritis',
            'message' => 'Stok obat kritis.',
            'severity' => Notification::SEVERITY_CRITICAL,
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Stok kritis');
    }

    public function test_user_cannot_mark_other_users_notification_as_read(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_PELANGGAN]);
        $other = User::factory()->create(['role' => User::ROLE_PELANGGAN]);
        $notification = Notification::create([
            'user_id' => $owner->id,
            'type' => 'order_status_changed',
            'title' => 'Privat',
            'message' => 'Tidak boleh dibaca user lain.',
        ]);

        Sanctum::actingAs($other);

        $this->patchJson("/api/notifications/{$notification->id}/read")->assertForbidden();
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $other->id,
            'status' => 'failed',
            'action' => 'mark_read',
            'module' => 'notification',
        ]);
    }

    public function test_unread_count_is_correct(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        Notification::create([
            'user_id' => $admin->id,
            'type' => 'personal',
            'title' => 'Personal',
            'message' => 'Belum dibaca.',
        ]);
        Notification::create([
            'role_target' => User::ROLE_ADMIN,
            'type' => 'role',
            'title' => 'Role',
            'message' => 'Belum dibaca.',
        ]);
        Notification::create([
            'role_target' => User::ROLE_ADMIN,
            'type' => 'role',
            'title' => 'Sudah dibaca',
            'message' => 'Sudah dibaca.',
            'read_at' => now(),
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('data.count', 2);
    }

    public function test_mark_read_sets_is_read_and_writes_audit_log(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_PELANGGAN]);
        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => 'order_status_changed',
            'title' => 'Order update',
            'message' => 'Order berubah.',
        ]);

        Sanctum::actingAs($user);

        $this->patchJson("/api/notifications/{$notification->id}/read")
            ->assertOk()
            ->assertJsonPath('data.is_read', true);

        $this->assertNotNull($notification->fresh()->read_at);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'mark_read',
            'module' => 'notification',
            'status' => 'success',
        ]);
    }

    public function test_mark_all_read_sets_visible_notifications_read(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $other = User::factory()->create(['role' => User::ROLE_PELANGGAN]);
        Notification::create(['user_id' => $admin->id, 'type' => 'personal', 'title' => 'A', 'message' => 'A']);
        Notification::create(['role_target' => User::ROLE_ADMIN, 'type' => 'role', 'title' => 'B', 'message' => 'B']);
        $otherNotification = Notification::create(['user_id' => $other->id, 'type' => 'other', 'title' => 'C', 'message' => 'C']);

        Sanctum::actingAs($admin);

        $this->patchJson('/api/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('data.count', 2);

        $this->assertSame(0, Notification::query()->where(function ($query) use ($admin): void {
            $query->where('user_id', $admin->id)->orWhere('role_target', $admin->role);
        })->whereNull('read_at')->count());
        $this->assertNull($otherNotification->fresh()->read_at);
    }

    public function test_notifications_are_sorted_unread_newest_first_then_read(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_PELANGGAN]);
        Notification::create(['user_id' => $user->id, 'type' => 'read_newer', 'title' => 'Read newer', 'message' => 'Read newer', 'read_at' => now()])
            ->forceFill(['created_at' => now()->addMinutes(3)])->save();
        Notification::create(['user_id' => $user->id, 'type' => 'unread_older', 'title' => 'Unread older', 'message' => 'Unread older'])
            ->forceFill(['created_at' => now()->addMinute()])->save();
        Notification::create(['user_id' => $user->id, 'type' => 'unread_newer', 'title' => 'Unread newer', 'message' => 'Unread newer'])
            ->forceFill(['created_at' => now()->addMinutes(2)])->save();

        Sanctum::actingAs($user);

        $this->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Unread newer')
            ->assertJsonPath('data.1.title', 'Unread older')
            ->assertJsonPath('data.2.title', 'Read newer');
    }

    public function test_checkout_creates_new_order_notifications_for_admin_and_kasir(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_PELANGGAN]);
        $medicine = $this->createMedicineWithBatch();
        $cart = Cart::create(['user_id' => $customer->id]);
        $cart->items()->create([
            'medicine_id' => $medicine->id,
            'quantity' => 1,
        ]);

        Sanctum::actingAs($customer);

        $this->postJson('/api/checkout', [
            'fulfillment_method' => Order::FULFILLMENT_PICKUP,
            'payment_method' => 'e_wallet',
            'customer_name' => 'Pelanggan Notifikasi',
            'customer_phone' => '08123456789',
        ])->assertCreated();

        $this->assertDatabaseHas('notifications', [
            'role_target' => User::ROLE_ADMIN,
            'type' => 'order_created',
        ]);
        $this->assertDatabaseHas('notifications', [
            'role_target' => User::ROLE_KASIR,
            'type' => 'order_created',
        ]);
    }

    public function test_order_status_change_creates_customer_notification(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $customer = User::factory()->create(['role' => User::ROLE_PELANGGAN]);
        $order = $this->createOrder($customer, [
            'status' => Order::STATUS_PAID,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
        ]);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/orders/{$order->id}/status", [
            'status' => Order::STATUS_CONFIRMED,
        ])->assertOk();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $customer->id,
            'type' => 'order_status_changed',
        ]);

        Sanctum::actingAs($customer);
        $this->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.target_url', "/my-orders/{$order->id}");
    }

    public function test_inventory_alert_command_creates_admin_and_apoteker_notifications(): void
    {
        $this->createMedicineWithBatch(minimumStock: 10, quantity: 2);

        $this->artisan('inventory:check-alerts')->assertSuccessful();

        $this->assertDatabaseHas('notifications', [
            'role_target' => User::ROLE_ADMIN,
            'type' => 'stock_critical',
        ]);
        $this->assertDatabaseHas('notifications', [
            'role_target' => User::ROLE_APOTEKER,
            'type' => 'stock_critical',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'status' => 'success',
            'action' => 'inventory_alert',
            'module' => 'notification',
        ]);
    }

    private function createMedicineWithBatch(int $minimumStock = 0, int $quantity = 20): Medicine
    {
        $category = Category::create(['name' => 'Notifikasi', 'description' => 'Kategori notifikasi.']);
        $medicine = Medicine::create([
            'category_id' => $category->id,
            'name' => 'Obat Notifikasi',
            'price' => 12000,
            'minimum_stock' => $minimumStock,
            'requires_prescription' => false,
            'is_active' => true,
        ]);
        MedicineBatch::create([
            'medicine_id' => $medicine->id,
            'batch_number' => 'NTF-001',
            'expired_date' => now()->addDays(20)->toDateString(),
            'quantity' => $quantity,
            'purchase_price' => 7000,
        ]);

        return $medicine;
    }

    private function createOrder(User $customer, array $attributes = []): Order
    {
        return Order::create(array_merge([
            'user_id' => $customer->id,
            'order_number' => 'ORD-NTF-'.str()->upper(str()->random(6)),
            'status' => Order::STATUS_PENDING_PAYMENT,
            'fulfillment_method' => Order::FULFILLMENT_PICKUP,
            'payment_method' => 'cashier',
            'payment_status' => Order::PAYMENT_STATUS_UNPAID,
            'subtotal' => 12000,
            'service_fee' => 0,
            'delivery_fee' => 0,
            'total' => 12000,
            'customer_name' => 'Pelanggan Notifikasi',
            'customer_phone' => '08123456789',
        ], $attributes));
    }
}
