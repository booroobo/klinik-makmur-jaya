<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Medicine;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Seed the catalog category taxonomy without creating duplicates.
     */
    public function run(): void
    {
        $categories = [
            ['name' => 'Semua Kategori', 'description' => 'Opsi untuk menampilkan seluruh kategori produk.'],
            ['name' => 'Obat Flu & Batuk', 'description' => 'Obat untuk keluhan flu, batuk, pilek, dan gejala pernapasan ringan.'],
            ['name' => 'Obat Demam & Nyeri', 'description' => 'Obat pereda demam dan nyeri ringan hingga sedang.'],
            ['name' => 'Obat Sakit Kepala', 'description' => 'Obat untuk meredakan sakit kepala dan migrain ringan.'],
            ['name' => 'Obat Pencernaan', 'description' => 'Obat untuk keluhan lambung, maag, diare, dan pencernaan.'],
            ['name' => 'Obat Alergi', 'description' => 'Obat untuk meredakan gejala alergi.'],
            ['name' => 'Obat Asma & Pernapasan', 'description' => 'Produk untuk mendukung keluhan asma dan saluran pernapasan.'],
            ['name' => 'Obat Kulit', 'description' => 'Obat dan perawatan untuk keluhan kulit.'],
            ['name' => 'Obat Mata & Telinga', 'description' => 'Obat untuk keluhan mata dan telinga.'],
            ['name' => 'Antibiotik', 'description' => 'Obat antibiotik yang memerlukan pengawasan dan resep dokter.'],
            ['name' => 'Antidiabetes', 'description' => 'Obat untuk terapi diabetes sesuai arahan tenaga kesehatan.'],
            ['name' => 'Antihipertensi', 'description' => 'Obat untuk membantu terapi tekanan darah tinggi.'],
            ['name' => 'Vitamin & Suplemen', 'description' => 'Vitamin dan suplemen pendukung daya tahan tubuh.'],
            ['name' => 'Jamu / Herbal', 'description' => 'Produk herbal dan jamu untuk dukungan kesehatan.'],
            ['name' => 'Obat Anak', 'description' => 'Obat dan produk kesehatan untuk anak.'],
            ['name' => 'Obat Cacing', 'description' => 'Obat untuk terapi infeksi cacing.'],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(
                ['name' => $category['name']],
                $category,
            );
        }

        $this->moveLegacyCategories();
    }

    private function moveLegacyCategories(): void
    {
        $legacyMap = [
            'Obat Resep' => 'Antibiotik',
            'Obat Bebas' => 'Obat Demam & Nyeri',
            'Suplemen' => 'Vitamin & Suplemen',
            'Alat Kesehatan' => 'Obat Demam & Nyeri',
        ];

        foreach ($legacyMap as $legacyName => $targetName) {
            $legacyCategory = Category::where('name', $legacyName)->first();
            $targetCategory = Category::where('name', $targetName)->first();

            if (! $legacyCategory || ! $targetCategory) {
                continue;
            }

            Medicine::withTrashed()->where('category_id', $legacyCategory->id)->update([
                'category_id' => $targetCategory->id,
            ]);

            $legacyCategory->delete();
        }
    }
}
