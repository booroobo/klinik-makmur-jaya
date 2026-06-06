<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\MedicineImport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MedicineSearchImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_autocomplete_search_returns_limited_results_with_filters(): void
    {
        $antibiotik = Category::create(['name' => 'Antibiotik']);
        $vitamin = Category::create(['name' => 'Vitamin']);

        Medicine::create([
            'category_id' => $antibiotik->id,
            'name' => 'Amoxicillin',
            'price' => 15000,
            'minimum_stock' => 0,
            'requires_prescription' => true,
            'is_active' => true,
        ]);
        Medicine::create([
            'category_id' => $vitamin->id,
            'name' => 'Amox Vitamin',
            'price' => 12000,
            'minimum_stock' => 0,
            'requires_prescription' => false,
            'is_active' => true,
        ]);

        $this->getJson("/api/catalog/medicines/autocomplete?q=amox&limit=1&category_id={$antibiotik->id}&requires_prescription=1")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Amoxicillin')
            ->assertJsonPath('data.0.category', 'Antibiotik');
    }

    public function test_autocomplete_handles_simple_typo(): void
    {
        $category = Category::create(['name' => 'Analgesik']);
        Medicine::create([
            'category_id' => $category->id,
            'name' => 'Paracetamol',
            'price' => 10000,
            'minimum_stock' => 0,
            'requires_prescription' => false,
            'is_active' => true,
        ]);

        $this->getJson('/api/catalog/medicines/autocomplete?q=paracitamol&limit=5')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Paracetamol');
    }

    public function test_catalog_places_out_of_stock_medicines_after_available_medicines(): void
    {
        $category = Category::create(['name' => 'Katalog']);
        $available = Medicine::create([
            'category_id' => $category->id,
            'name' => 'Obat Tersedia',
            'price' => 50000,
            'minimum_stock' => 0,
            'requires_prescription' => false,
            'is_active' => true,
        ]);
        $outOfStock = Medicine::create([
            'category_id' => $category->id,
            'name' => 'Obat Habis',
            'price' => 1000,
            'minimum_stock' => 0,
            'requires_prescription' => false,
            'is_active' => true,
        ]);

        MedicineBatch::create([
            'medicine_id' => $available->id,
            'batch_number' => 'AVAILABLE-001',
            'expired_date' => now()->addMonth()->toDateString(),
            'quantity' => 10,
            'purchase_price' => 25000,
        ]);
        MedicineBatch::create([
            'medicine_id' => $outOfStock->id,
            'batch_number' => 'EXPIRED-001',
            'expired_date' => now()->subDay()->toDateString(),
            'quantity' => 100,
            'purchase_price' => 500,
        ]);

        $this->getJson('/api/catalog/medicines?sort_price=asc')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Obat Tersedia')
            ->assertJsonPath('data.0.total_stock', 10)
            ->assertJsonPath('data.1.name', 'Obat Habis')
            ->assertJsonPath('data.1.total_stock', 0);
    }

    public function test_admin_can_upload_csv_import_and_sync_queue_processes_file(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        Sanctum::actingAs($admin);
        $path = tempnam(sys_get_temp_dir(), 'medicine-import-');
        file_put_contents($path, implode("\n", [
            'name,category,price,supplier,minimum_stock,requires_prescription,is_active,batch_number,expired_date,quantity,purchase_price',
            'Obat Import,Import Category,25000,Import Supplier,5,0,1,IMP-001,'.now()->addYear()->toDateString().',30,12000',
        ]));

        $file = new UploadedFile($path, 'medicines.csv', 'text/csv', null, true);

        $response = $this->postJson('/api/admin/medicines/import', [
            'file' => $file,
        ])->assertAccepted();

        $importId = $response->json('data.id');

        $this->assertDatabaseHas('medicine_imports', [
            'id' => $importId,
            'status' => MedicineImport::STATUS_COMPLETED,
            'processed_rows' => 1,
            'failed_rows' => 0,
        ]);
        $this->assertDatabaseHas('medicines', [
            'name' => 'Obat Import',
            'price' => 25000,
        ]);
        $this->assertDatabaseHas('medicine_batches', [
            'batch_number' => 'IMP-001',
            'quantity' => 30,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'import',
            'module' => 'medicine',
            'status' => 'success',
        ]);
    }

    public function test_admin_can_import_medicine_variants_and_variant_batches_from_csv(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        Sanctum::actingAs($admin);
        $path = tempnam(sys_get_temp_dir(), 'medicine-variant-import-');
        file_put_contents($path, implode("\n", [
            'name,category,has_variants,variant_name,variant_price,variant_sku,batch_number,expired_date,quantity,purchase_price',
            'Vicks,Obat Gosok,1,30ml,18000,VICKS-30,VICKS-30-001,'.now()->addYear()->toDateString().',12,9000',
            'Vicks,Obat Gosok,1,100ml,45000,VICKS-100,VICKS-100-001,'.now()->addYear()->toDateString().',7,22000',
        ]));

        $file = new UploadedFile($path, 'medicine-variants.csv', 'text/csv', null, true);

        $this->postJson('/api/admin/medicines/import', [
            'file' => $file,
        ])->assertAccepted();

        $this->assertDatabaseHas('medicine_imports', [
            'status' => MedicineImport::STATUS_COMPLETED,
            'processed_rows' => 2,
            'failed_rows' => 0,
        ]);
        $this->assertDatabaseHas('medicines', [
            'name' => 'Vicks',
            'has_variants' => true,
            'price' => 18000,
        ]);
        $medicineId = Medicine::where('name', 'Vicks')->value('id');
        $this->assertDatabaseHas('medicine_variants', [
            'medicine_id' => $medicineId,
            'name' => '30ml',
            'price' => 18000,
            'sku' => 'VICKS-30',
        ]);
        $this->assertDatabaseHas('medicine_variants', [
            'medicine_id' => $medicineId,
            'name' => '100ml',
            'price' => 45000,
            'sku' => 'VICKS-100',
        ]);
        $variantId = \App\Models\MedicineVariant::where('medicine_id', $medicineId)->where('name', '100ml')->value('id');
        $this->assertDatabaseHas('medicine_batches', [
            'medicine_id' => $medicineId,
            'medicine_variant_id' => $variantId,
            'batch_number' => 'VICKS-100-001',
            'quantity' => 7,
        ]);
    }

    public function test_non_admin_cannot_import_medicines(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_APOTEKER]));

        $this->postJson('/api/admin/medicines/import')->assertForbidden();
    }
}
