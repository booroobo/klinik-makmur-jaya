<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminReportTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_admin_can_access_sales_report(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));

        $this->getJson('/api/admin/reports/sales')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'period',
                    'summary' => ['total_transactions', 'total_revenue', 'average_order_value', 'items_sold'],
                    'trend',
                    'status_summary',
                    'payment_summary',
                ],
            ]);
    }

    public function test_non_admin_is_rejected_from_reports(): void
    {
        foreach ([User::ROLE_APOTEKER, User::ROLE_KASIR, User::ROLE_PELANGGAN] as $role) {
            Sanctum::actingAs(User::factory()->create(['role' => $role]));

            $this->getJson('/api/admin/reports/sales')->assertForbidden();
        }
    }

    public function test_sales_report_calculates_revenue_and_date_filter(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-05 10:00:00'));
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $customer = User::factory()->create(['role' => User::ROLE_PELANGGAN]);
        $medicine = $this->createMedicine();

        $included = $this->createOrder($customer, [
            'order_number' => 'ORD-RPT-IN',
            'status' => Order::STATUS_PAID,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'total' => 100000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $included->items()->create([
            'medicine_id' => $medicine->id,
            'medicine_name' => $medicine->name,
            'price' => 25000,
            'quantity' => 4,
            'subtotal' => 100000,
            'requires_prescription' => false,
        ]);

        $oldOrder = $this->createOrder($customer, [
            'order_number' => 'ORD-RPT-OLD',
            'status' => Order::STATUS_PAID,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'total' => 50000,
        ]);
        $oldOrder->forceFill([
            'created_at' => Carbon::parse('2026-05-26 10:00:00'),
            'updated_at' => Carbon::parse('2026-05-26 10:00:00'),
        ])->save();

        $this->createOrder($customer, [
            'order_number' => 'ORD-RPT-CANCEL',
            'status' => Order::STATUS_CANCELLED,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'total' => 70000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/reports/sales?date_from=2026-06-05&date_to=2026-06-05')
            ->assertOk()
            ->assertJsonPath('data.summary.total_transactions', 1)
            ->assertJsonPath('data.summary.total_revenue', 100000)
            ->assertJsonPath('data.summary.items_sold', 4);
    }

    public function test_top_medicines_matches_order_item_quantity(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-05 10:00:00'));
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $customer = User::factory()->create(['role' => User::ROLE_PELANGGAN]);
        $medicine = $this->createMedicine();
        $order = $this->createOrder($customer, [
            'status' => Order::STATUS_PAID,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'total' => 60000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $order->items()->create([
            'medicine_id' => $medicine->id,
            'medicine_name' => $medicine->name,
            'price' => 20000,
            'quantity' => 3,
            'subtotal' => 60000,
            'requires_prescription' => false,
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/reports/top-medicines?date_from=2026-06-05&date_to=2026-06-05')
            ->assertOk()
            ->assertJsonPath('data.0.medicine_name', $medicine->name)
            ->assertJsonPath('data.0.quantity_sold', 3)
            ->assertJsonPath('data.0.revenue', 60000);
    }

    public function test_expiring_medicines_report_shows_matching_batch(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-05 10:00:00'));
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $medicine = $this->createMedicine();
        MedicineBatch::create([
            'medicine_id' => $medicine->id,
            'batch_number' => 'EXP-001',
            'expired_date' => now()->addDays(20)->toDateString(),
            'quantity' => 10,
            'purchase_price' => 10000,
        ]);
        MedicineBatch::create([
            'medicine_id' => $medicine->id,
            'batch_number' => 'EXP-LATE',
            'expired_date' => now()->addDays(120)->toDateString(),
            'quantity' => 10,
            'purchase_price' => 10000,
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/reports/expiring-medicines')
            ->assertOk()
            ->assertJsonPath('data.0.batch_number', 'EXP-001')
            ->assertJsonPath('data.0.days_remaining', 20);
    }

    public function test_export_pdf_returns_pdf_response(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));

        $response = $this->get('/api/admin/reports/sales/export/pdf');

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));
    }

    public function test_export_excel_returns_excel_response(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));

        $response = $this->get('/api/admin/reports/sales/export/excel');

        $response->assertOk();
        $this->assertStringContainsString('vnd.ms-excel', $response->headers->get('content-type'));
    }

    public function test_reports_do_not_error_when_empty(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));

        $this->getJson('/api/admin/reports/sales')
            ->assertOk()
            ->assertJsonPath('data.summary.total_transactions', 0)
            ->assertJsonPath('data.summary.total_revenue', 0);
    }

    private function createMedicine(): Medicine
    {
        $category = Category::create(['name' => 'Report Category', 'description' => 'Kategori laporan.']);

        return Medicine::create([
            'category_id' => $category->id,
            'name' => 'Obat Report',
            'price' => 25000,
            'minimum_stock' => 0,
            'requires_prescription' => false,
            'is_active' => true,
        ]);
    }

    private function createOrder(User $customer, array $attributes = []): Order
    {
        return Order::create(array_merge([
            'user_id' => $customer->id,
            'order_number' => 'ORD-RPT-'.str()->upper(str()->random(6)),
            'status' => Order::STATUS_PENDING_PAYMENT,
            'fulfillment_method' => Order::FULFILLMENT_PICKUP,
            'payment_method' => 'cashier',
            'payment_status' => Order::PAYMENT_STATUS_UNPAID,
            'subtotal' => 25000,
            'service_fee' => 0,
            'delivery_fee' => 0,
            'total' => 25000,
            'customer_name' => 'Pelanggan Report',
            'customer_phone' => '08123456789',
        ], $attributes));
    }
}
