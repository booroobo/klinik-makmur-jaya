<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\MedicineVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MedicineVariantTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_medicine_with_variants_and_duplicate_names_are_rejected(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $category = Category::create(['name' => 'Balsem']);
        Sanctum::actingAs($admin);

        $this->postJson('/api/medicines', [
            'category_id' => $category->id,
            'name' => 'Vicks',
            'has_variants' => true,
            'variants' => [
                ['name' => '30ml', 'price' => 18000, 'sku' => 'VICKS-30'],
                ['name' => '100ml', 'price' => 45000, 'sku' => 'VICKS-100'],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.has_variants', true)
            ->assertJsonPath('data.price', '18000.00')
            ->assertJsonCount(2, 'data.variants');

        $this->postJson('/api/medicines', [
            'category_id' => $category->id,
            'name' => 'Produk Duplikat',
            'has_variants' => true,
            'variants' => [
                ['name' => '30ml', 'price' => 10000],
                ['name' => '30ML', 'price' => 12000],
            ],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('variants');
    }

    public function test_batch_requires_variant_that_belongs_to_medicine(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        [$medicine, $variant] = $this->variantMedicine();
        [$otherMedicine, $otherVariant] = $this->variantMedicine('Produk Lain', '50ml');
        Sanctum::actingAs($admin);

        $payload = [
            'medicine_id' => $medicine->id,
            'batch_number' => 'VICKS-001',
            'expired_date' => now()->addMonth()->toDateString(),
            'quantity' => 10,
            'purchase_price' => 10000,
        ];

        $this->postJson('/api/medicine-batches', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('medicine_variant_id');

        $this->postJson('/api/medicine-batches', $payload + [
            'medicine_variant_id' => $otherVariant->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('medicine_variant_id');

        $this->postJson('/api/medicine-batches', $payload + [
            'medicine_variant_id' => $variant->id,
        ])->assertCreated()
            ->assertJsonPath('data.medicine_variant_id', $variant->id);

        $this->assertNotSame($medicine->id, $otherMedicine->id);
    }

    public function test_catalog_cart_variant_change_and_checkout_use_variant_fifo_snapshot(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_PELANGGAN]);
        [$medicine, $small] = $this->variantMedicine();
        $large = MedicineVariant::create([
            'medicine_id' => $medicine->id,
            'name' => '100ml',
            'price' => 45000,
            'is_active' => true,
            'sort_order' => 2,
        ]);
        $firstLargeBatch = $this->batch($medicine, $large, 'LARGE-EARLY', 3, 10);
        $secondLargeBatch = $this->batch($medicine, $large, 'LARGE-LATE', 5, 30);
        $this->batch($medicine, $small, 'SMALL-001', 20, 20);

        $this->getJson("/api/catalog/medicines/{$medicine->id}")
            ->assertOk()
            ->assertJsonPath('data.variants.0.name', '30ml')
            ->assertJsonPath('data.variants.0.stock', 20)
            ->assertJsonPath('data.variants.1.name', '100ml')
            ->assertJsonPath('data.variants.1.stock', 8);

        Sanctum::actingAs($customer);

        $this->postJson('/api/cart/items', [
            'medicine_id' => $medicine->id,
            'quantity' => 4,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('medicine_variant_id');

        $cartResponse = $this->postJson('/api/cart/items', [
            'medicine_id' => $medicine->id,
            'medicine_variant_id' => $small->id,
            'quantity' => 4,
        ])->assertCreated()
            ->assertJsonPath('data.items.0.variant.name', '30ml')
            ->assertJsonPath('data.items.0.unit_price', 18000)
            ->assertJsonPath('data.items.0.available_stock', 20);

        $cartItemId = $cartResponse->json('data.items.0.id');

        $this->putJson("/api/cart/items/{$cartItemId}", [
            'quantity' => 4,
            'medicine_variant_id' => $large->id,
        ])->assertOk()
            ->assertJsonPath('data.items.0.variant.name', '100ml')
            ->assertJsonPath('data.items.0.line_total', 180000);

        $checkout = $this->postJson('/api/checkout', [
            'fulfillment_method' => 'pickup',
            'payment_method' => 'cashier',
            'customer_name' => 'Pelanggan Variant',
            'customer_phone' => '081234567890',
        ])->assertCreated()
            ->assertJsonPath('data.items.0.variant_name', '100ml')
            ->assertJsonPath('data.items.0.variant_price', '45000.00')
            ->assertJsonPath('data.items.0.price', '45000.00');

        $orderItemId = $checkout->json('data.items.0.id');
        $this->assertDatabaseHas('order_items', [
            'id' => $orderItemId,
            'medicine_variant_id' => $large->id,
            'variant_name' => '100ml',
            'variant_price' => 45000,
            'subtotal' => 180000,
        ]);
        $this->assertDatabaseHas('order_item_batches', [
            'order_item_id' => $orderItemId,
            'medicine_batch_id' => $firstLargeBatch->id,
            'quantity' => 3,
        ]);
        $this->assertDatabaseHas('order_item_batches', [
            'order_item_id' => $orderItemId,
            'medicine_batch_id' => $secondLargeBatch->id,
            'quantity' => 1,
        ]);
    }

    public function test_medicine_without_variants_keeps_legacy_cart_and_checkout_flow(): void
    {
        $category = Category::create(['name' => 'Umum']);
        $medicine = Medicine::create([
            'category_id' => $category->id,
            'name' => 'Paracetamol',
            'price' => 12000,
            'has_variants' => false,
            'minimum_stock' => 0,
            'requires_prescription' => false,
            'is_active' => true,
        ]);
        MedicineBatch::create([
            'medicine_id' => $medicine->id,
            'batch_number' => 'PARA-001',
            'expired_date' => now()->addMonth(),
            'quantity' => 10,
            'purchase_price' => 5000,
        ]);
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_PELANGGAN]));

        $this->postJson('/api/cart/items', [
            'medicine_id' => $medicine->id,
            'quantity' => 2,
        ])->assertCreated()
            ->assertJsonPath('data.items.0.medicine_variant_id', null)
            ->assertJsonPath('data.items.0.line_total', 24000);

        $this->postJson('/api/checkout', [
            'fulfillment_method' => 'pickup',
            'payment_method' => 'cashier',
            'customer_name' => 'Pelanggan Lama',
        ])->assertCreated()
            ->assertJsonPath('data.items.0.variant_name', null)
            ->assertJsonPath('data.items.0.price', '12000.00');
    }

    /**
     * @return array{Medicine, MedicineVariant}
     */
    private function variantMedicine(string $name = 'Vicks', string $variantName = '30ml'): array
    {
        $category = Category::firstOrCreate(['name' => 'Balsem']);
        $medicine = Medicine::create([
            'category_id' => $category->id,
            'name' => $name,
            'price' => 18000,
            'has_variants' => true,
            'minimum_stock' => 0,
            'requires_prescription' => false,
            'is_active' => true,
        ]);
        $variant = MedicineVariant::create([
            'medicine_id' => $medicine->id,
            'name' => $variantName,
            'price' => 18000,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        return [$medicine, $variant];
    }

    private function batch(
        Medicine $medicine,
        MedicineVariant $variant,
        string $number,
        int $quantity,
        int $days,
    ): MedicineBatch {
        return MedicineBatch::create([
            'medicine_id' => $medicine->id,
            'medicine_variant_id' => $variant->id,
            'batch_number' => $number,
            'expired_date' => now()->addDays($days),
            'quantity' => $quantity,
            'purchase_price' => 10000,
        ]);
    }
}
