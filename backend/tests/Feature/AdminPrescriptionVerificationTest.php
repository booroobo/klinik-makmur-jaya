<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\Order;
use App\Models\Prescription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminPrescriptionVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_prescriptions(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $prescription = $this->createPrescriptionOrder()['prescription'];

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/prescriptions')
            ->assertOk()
            ->assertJsonPath('data.0.id', $prescription->id);
    }

    public function test_apoteker_can_view_prescriptions(): void
    {
        $apoteker = User::factory()->create(['role' => User::ROLE_APOTEKER]);
        $prescription = $this->createPrescriptionOrder()['prescription'];

        Sanctum::actingAs($apoteker);

        $this->getJson('/api/admin/prescriptions')
            ->assertOk()
            ->assertJsonPath('data.0.id', $prescription->id);
    }

    public function test_kasir_and_pelanggan_cannot_access_prescriptions(): void
    {
        foreach ([User::ROLE_KASIR, User::ROLE_PELANGGAN] as $role) {
            Sanctum::actingAs(User::factory()->create(['role' => $role]));

            $this->getJson('/api/admin/prescriptions')->assertForbidden();
        }
    }

    public function test_approve_prescription_updates_review_fields_and_order_status(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $prescription = $this->createPrescriptionOrder()['prescription'];

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/prescriptions/{$prescription->id}/approve", [
            'notes' => 'Resep valid.',
        ])->assertOk()
            ->assertJsonPath('data.status', Prescription::STATUS_APPROVED)
            ->assertJsonPath('data.pharmacist_id', $admin->id)
            ->assertJsonPath('data.order.status', Order::STATUS_PENDING_PAYMENT);

        $this->assertDatabaseHas('prescriptions', [
            'id' => $prescription->id,
            'status' => Prescription::STATUS_APPROVED,
            'pharmacist_id' => $admin->id,
            'pharmacist_notes' => 'Resep valid.',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'status' => 'success',
            'action' => 'approve',
            'module' => 'prescription',
        ]);
    }

    public function test_approve_paid_prescription_order_moves_to_confirmed(): void
    {
        $apoteker = User::factory()->create(['role' => User::ROLE_APOTEKER]);
        $prescription = $this->createPrescriptionOrder([
            'payment_status' => Order::PAYMENT_STATUS_PAID,
        ])['prescription'];

        Sanctum::actingAs($apoteker);

        $this->patchJson("/api/admin/prescriptions/{$prescription->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.order.status', Order::STATUS_CONFIRMED);
    }

    public function test_reject_prescription_requires_reason_updates_order_and_restores_stock(): void
    {
        $apoteker = User::factory()->create(['role' => User::ROLE_APOTEKER]);
        $data = $this->createPrescriptionOrder();
        $prescription = $data['prescription'];
        $batch = $data['batch'];

        $this->assertSame(2, $batch->fresh()->quantity);

        Sanctum::actingAs($apoteker);

        $this->patchJson("/api/admin/prescriptions/{$prescription->id}/reject", [
            'reason' => 'Resep tidak terbaca.',
        ])->assertOk()
            ->assertJsonPath('data.status', Prescription::STATUS_REJECTED)
            ->assertJsonPath('data.order.status', Order::STATUS_REJECTED);

        $this->assertSame(5, $batch->fresh()->quantity);
        $this->assertDatabaseHas('prescriptions', [
            'id' => $prescription->id,
            'status' => Prescription::STATUS_REJECTED,
            'pharmacist_id' => $apoteker->id,
            'pharmacist_notes' => 'Resep tidak terbaca.',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $apoteker->id,
            'status' => 'success',
            'action' => 'reject',
            'module' => 'prescription',
        ]);
    }

    public function test_reject_prescription_validation_failure_is_logged(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $prescription = $this->createPrescriptionOrder()['prescription'];

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/prescriptions/{$prescription->id}/reject", [])
            ->assertUnprocessable();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'status' => 'failed',
            'action' => 'reject',
            'module' => 'prescription',
            'failure_reason' => 'Validation failed',
        ]);
    }

    public function test_final_prescription_cannot_be_processed_again(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $prescription = $this->createPrescriptionOrder()['prescription'];
        $prescription->update([
            'status' => Prescription::STATUS_APPROVED,
            'pharmacist_id' => $admin->id,
            'reviewed_at' => now(),
        ]);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/prescriptions/{$prescription->id}/reject", [
            'reason' => 'Coba proses ulang.',
        ])->assertUnprocessable();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'status' => 'failed',
            'action' => 'reject',
            'module' => 'prescription',
        ]);
    }

    /**
     * @return array{order: Order, prescription: Prescription, batch: MedicineBatch}
     */
    private function createPrescriptionOrder(array $orderAttributes = []): array
    {
        $customer = User::factory()->create(['role' => User::ROLE_PELANGGAN]);
        $category = Category::create(['name' => 'Obat Resep', 'description' => 'Kategori obat resep.']);
        $medicine = Medicine::create([
            'category_id' => $category->id,
            'name' => 'Amoxicillin Resep',
            'price' => 20000,
            'minimum_stock' => 0,
            'requires_prescription' => true,
            'is_active' => true,
        ]);
        $batch = MedicineBatch::create([
            'medicine_id' => $medicine->id,
            'batch_number' => 'RX-001',
            'expired_date' => now()->addYear()->toDateString(),
            'quantity' => 2,
            'purchase_price' => 12000,
        ]);
        $order = Order::create(array_merge([
            'user_id' => $customer->id,
            'order_number' => 'ORD-RX-'.str()->upper(str()->random(6)),
            'status' => Order::STATUS_WAITING_PRESCRIPTION_REVIEW,
            'fulfillment_method' => Order::FULFILLMENT_PICKUP,
            'payment_method' => 'cashier',
            'payment_status' => Order::PAYMENT_STATUS_UNPAID,
            'subtotal' => 60000,
            'service_fee' => 2500,
            'delivery_fee' => 0,
            'total' => 62500,
            'customer_name' => 'Pelanggan Resep',
            'customer_phone' => '08123456789',
        ], $orderAttributes));
        $item = $order->items()->create([
            'medicine_id' => $medicine->id,
            'medicine_name' => $medicine->name,
            'price' => $medicine->price,
            'quantity' => 3,
            'subtotal' => 60000,
            'requires_prescription' => true,
        ]);
        $item->batchUsages()->create([
            'medicine_batch_id' => $batch->id,
            'quantity' => 3,
            'unit_cost' => $batch->purchase_price,
            'expiry_date' => $batch->expired_date,
        ]);
        $prescription = $order->prescription()->create([
            'user_id' => $customer->id,
            'file_path' => 'prescriptions/test-prescription.pdf',
            'status' => Prescription::STATUS_PENDING,
        ]);

        return [
            'order' => $order,
            'prescription' => $prescription,
            'batch' => $batch,
        ];
    }
}
