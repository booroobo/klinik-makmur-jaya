<?php

namespace App\Console\Commands;

use App\Models\MedicineDraft;
use Illuminate\Console\Command;

class ClearExpiredMedicineDrafts extends Command
{
    protected $signature = 'medicine-drafts:clear-expired';

    protected $description = 'Delete expired medicine drafts and their draft images.';

    public function handle(): int
    {
        $drafts = MedicineDraft::query()
            ->where('expires_at', '<', now())
            ->get();

        $drafts->each(function (MedicineDraft $draft): void {
            $draft->deleteDraftImage();
            $draft->delete();
        });

        $this->info("Deleted {$drafts->count()} expired medicine draft(s).");

        return self::SUCCESS;
    }
}
