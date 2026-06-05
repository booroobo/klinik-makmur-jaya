<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class CheckInventoryAlertsCommand extends Command
{
    protected $signature = 'inventory:check-alerts';

    protected $description = 'Generate in-app notifications for critical stock and expiring medicine batches.';

    public function handle(NotificationService $notificationService): int
    {
        $created = 0;

        $criticalMedicines = Medicine::query()
            ->where('is_active', true)
            ->with(['category:id,name', 'batches' => fn ($query) => $query
                ->whereDate('expired_date', '>=', now()->toDateString())])
            ->get()
            ->filter(fn (Medicine $medicine): bool => $medicine->total_stock <= $medicine->minimum_stock);

        foreach ($criticalMedicines as $medicine) {
            $created += $notificationService->notifyInventoryAlert(
                'stock_critical',
                "Stok {$medicine->name} kritis",
                "Sisa stok {$medicine->total_stock}, minimum {$medicine->minimum_stock}.",
                Notification::SEVERITY_CRITICAL,
                [
                    'medicine_id' => $medicine->id,
                    'medicine_name' => $medicine->name,
                    'total_stock' => $medicine->total_stock,
                    'minimum_stock' => $medicine->minimum_stock,
                ],
            );
        }

        $expiringBatches = MedicineBatch::query()
            ->with('medicine:id,name,is_active')
            ->where('quantity', '>', 0)
            ->whereDate('expired_date', '>=', now()->toDateString())
            ->whereDate('expired_date', '<=', now()->copy()->addDays(90)->toDateString())
            ->whereHas('medicine', fn ($query) => $query->where('is_active', true))
            ->orderBy('expired_date')
            ->get();

        foreach ($expiringBatches as $batch) {
            $days = now()->startOfDay()->diffInDays($batch->expired_date, false);
            $bucket = match (true) {
                $days <= 30 => '30',
                $days <= 60 => '60',
                default => '90',
            };

            $created += $notificationService->notifyInventoryAlert(
                'batch_expiring',
                "Batch {$batch->batch_number} mendekati kedaluwarsa",
                "{$batch->medicine?->name} akan kedaluwarsa dalam {$days} hari.",
                $days <= 30 ? Notification::SEVERITY_WARNING : Notification::SEVERITY_INFO,
                [
                    'medicine_batch_id' => $batch->id,
                    'medicine_id' => $batch->medicine_id,
                    'medicine_name' => $batch->medicine?->name,
                    'batch_number' => $batch->batch_number,
                    'expired_date' => $batch->expired_date?->toDateString(),
                    'days_remaining' => $days,
                    'bucket' => $bucket,
                ],
            );
        }

        AuditLog::create([
            'status' => 'success',
            'action' => 'inventory_alert',
            'module' => 'notification',
            'description' => "Command inventory:check-alerts membuat {$created} notifikasi.",
            'metadata' => [
                'critical_medicine_count' => $criticalMedicines->count(),
                'expiring_batch_count' => $expiringBatches->count(),
                'created_notifications' => $created,
            ],
        ]);

        $this->info("Created {$created} inventory alert notifications.");

        return self::SUCCESS;
    }
}
