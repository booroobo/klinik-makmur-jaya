<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\Order;
use App\Models\Prescription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_admin_can_access_dashboard(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));

        $this->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'summary' => [
                        'revenue_today',
                        'revenue_week',
                        'revenue_month',
                        'orders_today',
                        'customers',
                        'active_medicines',
                        'pending_prescriptions',
                    ],
                    'orders_by_status',
                    'recent_orders',
                    'critical_stock_medicines',
                    'expiring_batches',
                    'top_selling_medicines',
                    'sales_daily',
                    'sales_monthly',
                ],
            ]);
    }

    public function test_non_admin_cannot_access_dashboard(): void
    {
        foreach ([User::ROLE_APOTEKER, User::ROLE_KASIR, User::ROLE_PELANGGAN] as $role) {
            Sanctum::actingAs(User::factory()->create(['role' => $role]));

            $this->getJson('/api/admin/dashboard')->assertForbidden();
        }
    }

    public function test_dashboard_numbers_match_simple_order_data(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-05 10:00:00'));
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $customer = User::factory()->create(['role' => User::ROLE_PELANGGAN]);
        $category = Category::create(['name' => 'Dashboard', 'description' => 'Dashboard category.']);
        $medicine = Medicine::create([
            'category_id' => $category->id,
            'name' => 'Obat Dashboard',
            'price' => 25000,
            'minimum_stock' => 5,
            'requires_prescription' => true,
            'is_active' => true,
        ]);
        MedicineBatch::create([
            'medicine_id' => $medicine->id,
            'batch_number' => 'DASH-001',
            'expired_date' => now()->addDays(20)->toDateString(),
            'quantity' => 2,
            'purchase_price' => 10000,
        ]);

        $paidOrder = $this->createOrder($customer, [
            'order_number' => 'ORD-DASH-PAID',
            'status' => Order::STATUS_PAID,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'total' => 100000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $paidOrder->items()->create([
            'medicine_id' => $medicine->id,
            'medicine_name' => $medicine->name,
            'price' => 25000,
            'quantity' => 4,
            'subtotal' => 100000,
            'requires_prescription' => true,
        ]);

        $unpaidOrder = $this->createOrder($customer, [
            'order_number' => 'ORD-DASH-UNPAID',
            'status' => Order::STATUS_PENDING_PAYMENT,
            'payment_status' => Order::PAYMENT_STATUS_UNPAID,
            'total' => 50000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $unpaidOrder->prescription()->create([
            'user_id' => $customer->id,
            'file_path' => 'prescriptions/test.pdf',
            'status' => Prescription::STATUS_PENDING,
        ]);

        $this->createOrder($customer, [
            'order_number' => 'ORD-DASH-REJECT',
            'status' => Order::STATUS_REJECTED,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'total' => 70000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('data.summary.revenue_today', 100000)
            ->assertJsonPath('data.summary.revenue_week', 100000)
            ->assertJsonPath('data.summary.revenue_month', 100000)
            ->assertJsonPath('data.summary.orders_today', 3)
            ->assertJsonPath('data.summary.customers', 1)
            ->assertJsonPath('data.summary.active_medicines', 1)
            ->assertJsonPath('data.summary.pending_prescriptions', 1)
            ->assertJsonPath('data.orders_by_status.paid', 1)
            ->assertJsonPath('data.orders_by_status.pending_payment', 1)
            ->assertJsonPath('data.orders_by_status.rejected', 1)
            ->assertJsonPath('data.critical_stock_medicines.0.name', 'Obat Dashboard')
            ->assertJsonPath('data.expiring_batches.0.batch_number', 'DASH-001')
            ->assertJsonPath('data.top_selling_medicines.0.quantity_sold', 4);
    }

    public function test_dashboard_does_not_error_when_database_has_no_business_data(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));

        $this->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('data.summary.revenue_today', 0)
            ->assertJsonPath('data.summary.orders_today', 0)
            ->assertJsonPath('data.recent_orders', [])
            ->assertJsonPath('data.critical_stock_medicines', []);
    }

    private function createOrder(User $customer, array $attributes = []): Order
    {
        return Order::create(array_merge([
            'user_id' => $customer->id,
            'order_number' => 'ORD-DASH-'.str()->upper(str()->random(6)),
            'status' => Order::STATUS_PENDING_PAYMENT,
            'fulfillment_method' => Order::FULFILLMENT_PICKUP,
            'payment_method' => 'cashier',
            'payment_status' => Order::PAYMENT_STATUS_UNPAID,
            'subtotal' => 50000,
            'service_fee' => 0,
            'delivery_fee' => 0,
            'total' => 50000,
            'customer_name' => 'Pelanggan Dashboard',
            'customer_phone' => '08123456789',
        ], $attributes));
    }
}
