<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Cart;
use App\Models\Medicine;
use App\Models\Order;
use App\Models\ReportJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportQueuePaymentSimulationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_queue_large_report_and_finish_job(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        Sanctum::actingAs($admin);
        $this->seedReportData();

        $response = $this->postJson('/api/admin/reports/queue', [
            'format' => 'pdf',
            'date_from' => now()->subDays(7)->toDateString(),
            'date_to' => now()->toDateString(),
            'group_by' => 'daily',
        ]);

        $response->assertAccepted()
            ->assertJsonPath('data.status', ReportJob::STATUS_FINISHED)
            ->assertJsonPath('data.progress', 100)
            ->assertJsonPath('data.format', 'pdf');

        $reportJobId = $response->json('data.id');
        $reportJob = ReportJob::findOrFail($reportJobId);

        $this->assertSame(ReportJob::STATUS_FINISHED, $reportJob->status);
        $this->assertNotNull($reportJob->file_path);
        Storage::disk('local')->assertExists($reportJob->file_path);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'report_queue',
            'module' => 'report',
            'status' => 'success',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'report_job_start',
            'module' => 'report',
            'status' => 'success',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'report_job_finish',
            'module' => 'report',
            'status' => 'success',
        ]);

        $this->getJson('/api/admin/reports/queue/'.$reportJobId)
            ->assertOk()
            ->assertJsonPath('data.status', ReportJob::STATUS_FINISHED);

        $this->get('/api/admin/reports/queue/'.$reportJobId.'/download')
            ->assertOk();
    }

    public function test_checkout_uses_simulated_payment_status(): void
    {
        [$user, $medicine] = $this->seedCheckoutMedicine();
        $cart = Cart::create(['user_id' => $user->id]);
        $cart->items()->create([
            'medicine_id' => $medicine->id,
            'quantity' => 1,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/checkout', [
            'fulfillment_method' => 'pickup',
            'payment_method' => 'e_wallet',
            'payment_status' => 'failed',
            'customer_name' => $user->name,
            'customer_phone' => $user->phone,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.payment_status', 'failed');

        $order = Order::firstOrFail();
        $this->assertSame('failed', $order->payment_status);
        $this->assertSame(Order::STATUS_PENDING_PAYMENT, $order->normalizedStatus());
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'payment_simulation',
            'module' => 'order',
            'status' => 'success',
        ]);
    }

    /**
     * @return array{0: User, 1: Medicine}
     */
    private function seedCheckoutMedicine(): array
    {
        $user = User::factory()->create(['role' => User::ROLE_PELANGGAN]);
        $category = Category::create([
            'name' => 'Payment Category',
            'description' => 'Kategori payment test.',
        ]);
        $medicine = Medicine::create([
            'category_id' => $category->id,
            'name' => 'Obat Payment',
            'price' => 20000,
            'minimum_stock' => 0,
            'requires_prescription' => false,
            'is_active' => true,
        ]);
        $medicine->batches()->create([
            'batch_number' => 'PAY-001',
            'expired_date' => now()->addMonths(3)->toDateString(),
            'quantity' => 5,
            'purchase_price' => 10000,
        ]);

        return [$user, $medicine];
    }

    private function seedReportData(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_PELANGGAN]);
        $category = Category::create([
            'name' => 'Report Queue Category',
            'description' => 'Kategori queue report.',
        ]);
        $medicine = Medicine::create([
            'category_id' => $category->id,
            'name' => 'Obat Report Queue',
            'price' => 25000,
            'minimum_stock' => 0,
            'requires_prescription' => false,
            'is_active' => true,
        ]);

        Order::create([
            'user_id' => $customer->id,
            'order_number' => 'ORD-QUEUE-001',
            'status' => Order::STATUS_PAID,
            'fulfillment_method' => Order::FULFILLMENT_PICKUP,
            'payment_method' => 'e_wallet',
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'subtotal' => 25000,
            'service_fee' => 0,
            'delivery_fee' => 0,
            'total' => 25000,
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
        ])->items()->create([
            'medicine_id' => $medicine->id,
            'medicine_name' => $medicine->name,
            'price' => 25000,
            'quantity' => 1,
            'subtotal' => 25000,
            'requires_prescription' => false,
        ]);
    }
}
