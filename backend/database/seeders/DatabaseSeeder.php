<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $users = [
            [
                'name' => 'Admin Klinik',
                'email' => 'admin@example.com',
                'role' => User::ROLE_ADMIN,
            ],
            [
                'name' => 'Apoteker Klinik',
                'email' => 'apoteker@example.com',
                'role' => User::ROLE_APOTEKER,
            ],
            [
                'name' => 'Kasir Klinik',
                'email' => 'kasir@example.com',
                'role' => User::ROLE_KASIR,
            ],
            [
                'name' => 'Pelanggan Klinik',
                'email' => 'pelanggan@example.com',
                'role' => User::ROLE_PELANGGAN,
            ],
        ];

        foreach ($users as $user) {
            User::updateOrCreate(
                ['email' => $user['email']],
                $user + ['password' => Hash::make('password'), 'email_verified_at' => now()],
            );
        }

        $this->call(CategorySeeder::class);

        $categories = Category::whereIn('name', [
            'Obat Demam & Nyeri',
            'Obat Alergi',
            'Obat Asma & Pernapasan',
            'Obat Pencernaan',
            'Antibiotik',
            'Vitamin & Suplemen',
        ])->get()->keyBy('name');

        $suppliers = collect([
            [
                'name' => 'PT Kimia Farma Trading',
                'phone' => '(021) 1500-255',
                'address' => 'Jl. Veteran No. 9, Jakarta',
                'email' => 'sales@kimiafarma.co.id',
            ],
            [
                'name' => 'PT Enseval Putera Megatrading',
                'phone' => '(021) 4682-2422',
                'address' => 'Kawasan Industri Pulogadung, Jakarta',
                'email' => 'order@enseval.com',
            ],
            [
                'name' => 'PT Anugerah Pharmindo Lestari',
                'phone' => '(021) 5790-9999',
                'address' => 'Jl. Letjen S. Parman, Jakarta',
                'email' => 'cs@aplcare.co.id',
            ],
        ])->mapWithKeys(fn (array $supplier) => [
            $supplier['name'] => Supplier::updateOrCreate(
                ['name' => $supplier['name']],
                $supplier,
            ),
        ]);

        $medicines = [
            [
                'name' => 'Paracetamol 500mg',
                'category' => 'Obat Demam & Nyeri',
                'supplier' => 'PT Kimia Farma Trading',
                'description' => 'Meredakan demam, sakit kepala, dan nyeri ringan.',
                'composition' => 'Paracetamol 500mg',
                'dosage' => '1 tablet 3 kali sehari sesudah makan.',
                'side_effects' => 'Mual ringan pada sebagian pengguna.',
                'price' => 12500,
                'minimum_stock' => 50,
                'requires_prescription' => false,
            ],
            [
                'name' => 'Amoxicillin 500mg',
                'category' => 'Antibiotik',
                'supplier' => 'PT Enseval Putera Megatrading',
                'description' => 'Antibiotik untuk infeksi bakteri sesuai resep dokter.',
                'composition' => 'Amoxicillin trihydrate 500mg',
                'dosage' => 'Sesuai instruksi dokter.',
                'side_effects' => 'Alergi, mual, diare.',
                'price' => 25000,
                'minimum_stock' => 30,
                'requires_prescription' => true,
            ],
            [
                'name' => 'Cetirizine 10mg',
                'category' => 'Obat Alergi',
                'supplier' => 'PT Anugerah Pharmindo Lestari',
                'description' => 'Membantu meredakan gejala alergi.',
                'composition' => 'Cetirizine dihydrochloride 10mg',
                'dosage' => '1 tablet sehari.',
                'side_effects' => 'Mengantuk.',
                'price' => 18000,
                'minimum_stock' => 40,
                'requires_prescription' => false,
            ],
            [
                'name' => 'Vitamin C 500mg',
                'category' => 'Vitamin & Suplemen',
                'supplier' => 'PT Kimia Farma Trading',
                'description' => 'Suplemen vitamin C untuk daya tahan tubuh.',
                'composition' => 'Ascorbic acid 500mg',
                'dosage' => '1 tablet sehari.',
                'side_effects' => 'Gangguan lambung jika berlebihan.',
                'price' => 35000,
                'minimum_stock' => 35,
                'requires_prescription' => false,
            ],
            [
                'name' => 'Enervon-C 30 Tablet',
                'category' => 'Vitamin & Suplemen',
                'supplier' => 'PT Enseval Putera Megatrading',
                'description' => 'Multivitamin untuk membantu menjaga stamina.',
                'composition' => 'Vitamin C, vitamin B kompleks, nicotinamide.',
                'dosage' => '1 tablet sehari setelah makan.',
                'side_effects' => 'Urin lebih kuning.',
                'price' => 48900,
                'minimum_stock' => 25,
                'requires_prescription' => false,
            ],
            [
                'name' => 'Sterimar Nasal Spray',
                'category' => 'Obat Asma & Pernapasan',
                'supplier' => 'PT Anugerah Pharmindo Lestari',
                'description' => 'Semprotan hidung berbahan air laut isotonis.',
                'composition' => 'Larutan air laut steril.',
                'dosage' => 'Semprotkan sesuai kebutuhan.',
                'side_effects' => 'Iritasi ringan pada hidung sensitif.',
                'price' => 175000,
                'minimum_stock' => 10,
                'requires_prescription' => false,
            ],
            [
                'name' => 'Omeprazole 20mg',
                'category' => 'Obat Pencernaan',
                'supplier' => 'PT Kimia Farma Trading',
                'description' => 'Mengurangi produksi asam lambung.',
                'composition' => 'Omeprazole 20mg',
                'dosage' => 'Sesuai instruksi dokter.',
                'side_effects' => 'Sakit kepala, mual.',
                'price' => 32000,
                'minimum_stock' => 20,
                'requires_prescription' => true,
            ],
            [
                'name' => 'Termometer Digital',
                'category' => 'Obat Demam & Nyeri',
                'supplier' => 'PT Enseval Putera Megatrading',
                'description' => 'Termometer digital untuk pengukuran suhu tubuh.',
                'composition' => 'Perangkat digital dengan sensor suhu.',
                'dosage' => null,
                'side_effects' => null,
                'price' => 65000,
                'minimum_stock' => 15,
                'requires_prescription' => false,
            ],
        ];

        foreach ($medicines as $index => $medicineData) {
            $medicine = Medicine::updateOrCreate(
                ['name' => $medicineData['name']],
                [
                    'category_id' => $categories[$medicineData['category']]->id,
                    'supplier_id' => $suppliers[$medicineData['supplier']]->id,
                    'description' => $medicineData['description'],
                    'composition' => $medicineData['composition'],
                    'dosage' => $medicineData['dosage'],
                    'side_effects' => $medicineData['side_effects'],
                    'price' => $medicineData['price'],
                    'minimum_stock' => $medicineData['minimum_stock'],
                    'requires_prescription' => $medicineData['requires_prescription'],
                    'is_active' => true,
                ],
            );

            foreach ([1, 2] as $batchIndex) {
                MedicineBatch::updateOrCreate(
                    [
                        'medicine_id' => $medicine->id,
                        'batch_number' => sprintf('BATCH-%03d-%02d', $index + 1, $batchIndex),
                    ],
                    [
                        'expired_date' => now()->addMonths(($index + 1) * $batchIndex + 3)->toDateString(),
                        'quantity' => 20 + ($index * 8) + ($batchIndex * 12),
                        'purchase_price' => max(1000, (float) $medicineData['price'] * 0.65),
                    ],
                );
            }
        }
    }
}
