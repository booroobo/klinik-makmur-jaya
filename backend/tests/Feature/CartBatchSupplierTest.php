<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CartBatchSupplierTest extends TestCase
{
    use RefreshDatabase;

    public function test_typed_cart_quantity_cannot_exceed_active_stock(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_PELANGGAN]);
        $medicine = $this->medicine();
        MedicineBatch::create([
            'medicine_id' => $medicine->id,
            'batch_number' => 'ACTIVE-1',
            'expired_date' => now()->addMonth()->toDateString(),
            'quantity' => 3,
            'purchase_price' => 1000,
        ]);

        Sanctum::actingAs($customer);
        $itemId = $this->postJson('/api/cart/items', ['medicine_id' => $medicine->id, 'quantity' => 1])
            ->assertCreated()
            ->json('data.items.0.id');

        $this->putJson("/api/cart/items/{$itemId}", ['quantity' => 4])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Jumlah melebihi stok tersedia.');
    }

    public function test_expired_batch_cannot_be_created(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        Sanctum::actingAs($admin);

        $this->postJson('/api/medicine-batches', [
            'medicine_id' => $this->medicine()->id,
            'batch_number' => 'EXPIRED-1',
            'expired_date' => now()->subDay()->toDateString(),
            'quantity' => 5,
        ])->assertUnprocessable()->assertJsonValidationErrors('expired_date');
    }

    public function test_admin_can_undo_supplier_delete(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $supplier = Supplier::create(['name' => 'Supplier Undo']);
        Sanctum::actingAs($admin);

        $this->deleteJson("/api/suppliers/{$supplier->id}")->assertOk();
        $this->assertSoftDeleted('suppliers', ['id' => $supplier->id]);

        $this->postJson("/api/suppliers/{$supplier->id}/restore")
            ->assertOk()
            ->assertJsonPath('data.name', 'Supplier Undo');
        $this->assertNotSoftDeleted('suppliers', ['id' => $supplier->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'restore', 'module' => 'supplier']);
    }

    private function medicine(): Medicine
    {
        $category = Category::firstOrCreate(['name' => 'Test'], ['description' => 'Test']);

        return Medicine::create([
            'category_id' => $category->id,
            'name' => 'Obat '.str()->random(6),
            'price' => 5000,
            'minimum_stock' => 0,
            'requires_prescription' => false,
            'is_active' => true,
        ]);
    }
}
