<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\MedicineImport;
use App\Models\MedicineVariant;
use App\Models\Supplier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ImportMedicinesJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $medicineImportId) {}

    public function handle(): void
    {
        $import = MedicineImport::findOrFail($this->medicineImportId);
        $import->update([
            'status' => MedicineImport::STATUS_PROCESSING,
            'started_at' => now(),
            'errors' => [],
        ]);

        try {
            $rows = $this->rows($import);
            $errors = [];
            $processed = 0;
            $failed = 0;

            $import->update(['total_rows' => count($rows)]);

            foreach ($rows as $index => $row) {
                try {
                    $this->importRow($row);
                    $processed++;
                } catch (Throwable $exception) {
                    $failed++;
                    $errors[] = [
                        'row' => $index + 2,
                        'message' => $exception->getMessage(),
                    ];
                }

                $import->update([
                    'processed_rows' => $processed,
                    'failed_rows' => $failed,
                    'errors' => $errors,
                ]);
            }

            $import->update([
                'status' => $failed > 0 ? MedicineImport::STATUS_FAILED : MedicineImport::STATUS_COMPLETED,
                'finished_at' => now(),
            ]);

            AuditLog::create([
                'user_id' => $import->user_id,
                'status' => $failed > 0 ? 'failed' : 'success',
                'action' => 'import',
                'module' => 'medicine',
                'description' => "Import obat {$import->original_filename} selesai.",
                'failure_reason' => $failed > 0 ? 'Some rows failed' : null,
                'metadata' => [
                    'medicine_import_id' => $import->id,
                    'processed_rows' => $processed,
                    'failed_rows' => $failed,
                ],
            ]);
        } catch (Throwable $exception) {
            $import->update([
                'status' => MedicineImport::STATUS_FAILED,
                'errors' => [['row' => null, 'message' => $exception->getMessage()]],
                'finished_at' => now(),
            ]);

            AuditLog::create([
                'user_id' => $import->user_id,
                'status' => 'failed',
                'action' => 'import',
                'module' => 'medicine',
                'description' => "Import obat {$import->original_filename} gagal.",
                'failure_reason' => $exception->getMessage(),
                'metadata' => ['medicine_import_id' => $import->id],
            ]);

            throw $exception;
        }
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    private function rows(MedicineImport $import): array
    {
        $extension = strtolower(pathinfo($import->original_filename, PATHINFO_EXTENSION));

        if ($extension === 'xlsx') {
            throw new \RuntimeException('Import XLSX membutuhkan ekstensi ZipArchive/PHPSpreadsheet yang belum tersedia di environment ini. Gunakan CSV untuk import.');
        }

        $stream = Storage::readStream($import->file_path);
        if (! $stream) {
            throw new \RuntimeException('File import tidak ditemukan.');
        }

        $headers = null;
        $rows = [];

        while (($values = fgetcsv($stream, 0, ',')) !== false) {
            if ($headers === null) {
                $headers = array_map(fn ($header) => strtolower(trim((string) $header)), $values);
                $this->validateHeaders($headers);
                continue;
            }

            if (count(array_filter($values, fn ($value) => $value !== null && trim((string) $value) !== '')) === 0) {
                continue;
            }

            $rows[] = array_combine($headers, array_pad($values, count($headers), null));
        }

        fclose($stream);

        return $rows;
    }

    /**
     * @param array<int, string> $headers
     */
    private function validateHeaders(array $headers): void
    {
        $required = ['name', 'category'];
        $missing = array_diff($required, $headers);

        if ($missing !== []) {
            throw new \InvalidArgumentException('Kolom wajib tidak ada: '.implode(', ', $missing).'.');
        }
    }

    /**
     * @param array<string, string|null> $row
     */
    private function importRow(array $row): void
    {
        $name = trim((string) ($row['name'] ?? ''));
        $categoryName = trim((string) ($row['category'] ?? ''));
        $price = $this->number($row['price'] ?? null);
        $variantName = trim((string) ($row['variant_name'] ?? $row['variant'] ?? ''));
        $variantPrice = $this->number($row['variant_price'] ?? null);
        $hasVariants = $this->boolean($row['has_variants'] ?? ($variantName !== ''));

        if ($name === '' || $categoryName === '') {
            throw new \InvalidArgumentException('Nama dan kategori wajib diisi.');
        }

        if ($hasVariants && ($variantName === '' || $variantPrice <= 0)) {
            throw new \InvalidArgumentException('variant_name dan variant_price wajib valid untuk obat bervarian.');
        }

        if (! $hasVariants && $price < 0) {
            throw new \InvalidArgumentException('Harga wajib valid.');
        }

        $category = Category::firstOrCreate(['name' => $categoryName]);
        $supplier = null;
        $supplierName = trim((string) ($row['supplier'] ?? $row['supplier_name'] ?? ''));

        if ($supplierName !== '') {
            $supplier = Supplier::firstOrCreate(['name' => $supplierName]);
        }

        $medicine = Medicine::updateOrCreate(
            ['name' => $name],
            [
                'category_id' => $category->id,
                'supplier_id' => $supplier?->id,
                'description' => $row['description'] ?? null,
                'composition' => $row['composition'] ?? null,
                'dosage' => $row['dosage'] ?? null,
                'side_effects' => $row['side_effects'] ?? null,
                'price' => $hasVariants ? max($variantPrice, 0) : $price,
                'has_variants' => $hasVariants,
                'minimum_stock' => (int) $this->number($row['minimum_stock'] ?? 0),
                'requires_prescription' => $this->boolean($row['requires_prescription'] ?? false),
                'is_active' => $this->boolean($row['is_active'] ?? true),
            ],
        );

        $variant = null;

        if ($hasVariants) {
            $variant = MedicineVariant::withTrashed()->firstOrNew([
                'medicine_id' => $medicine->id,
                'name' => $variantName,
            ]);

            if ($variant->trashed()) {
                $variant->restore();
            }

            $variant->fill([
                'price' => $variantPrice,
                'sku' => filled($row['variant_sku'] ?? null) ? trim((string) $row['variant_sku']) : $variant->sku,
                'is_active' => $this->boolean($row['variant_is_active'] ?? true),
                'sort_order' => isset($row['variant_sort_order']) && $row['variant_sort_order'] !== ''
                    ? (int) $this->number($row['variant_sort_order'])
                    : $variant->sort_order,
            ])->save();

            $lowestVariantPrice = (float) $medicine->variants()
                ->where('is_active', true)
                ->min('price');

            if ($lowestVariantPrice > 0 && (float) $medicine->price !== $lowestVariantPrice) {
                $medicine->update(['price' => $lowestVariantPrice]);
            }
        }

        $batchNumber = trim((string) ($row['batch_number'] ?? ''));
        $expiredDate = trim((string) ($row['expired_date'] ?? ''));

        if ($batchNumber !== '' && $expiredDate !== '') {
            MedicineBatch::updateOrCreate(
                [
                    'medicine_id' => $medicine->id,
                    'medicine_variant_id' => $variant?->id,
                    'batch_number' => $batchNumber,
                ],
                [
                    'medicine_variant_id' => $variant?->id,
                    'expired_date' => $expiredDate,
                    'quantity' => (int) $this->number($row['quantity'] ?? 0),
                    'purchase_price' => $this->number($row['purchase_price'] ?? 0),
                ],
            );
        }
    }

    private function number(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0;
        }

        return (float) str_replace(',', '.', preg_replace('/[^0-9,.\-]/', '', (string) $value));
    }

    private function boolean(mixed $value): bool
    {
        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'ya', 'y'], true);
    }
}
