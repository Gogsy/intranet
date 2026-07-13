<?php

namespace App\Console\Commands;

use App\Models\BudgetYear;
use App\Services\InvoiceTrackerSync;
use Illuminate\Console\Command;

/**
 * Safety net for the observer-based Budget Planner -> Invoice Tracker mirror:
 * wipes and rebuilds the synced plan rows from each year's pointed version.
 * Idempotent; manual tracker rows are never touched.
 */
class ResyncInvoiceTracker extends Command
{
    protected $signature = 'invoice-tracker:resync {year? : Budget year (e.g. 2026); all years when omitted}';

    protected $description = 'Rebuild Invoice Tracker plan rows from each budget year\'s tracker-source version';

    public function handle(): int
    {
        $years = BudgetYear::query()
            ->when($this->argument('year'), fn ($q, $year) => $q->where('year', (int) $year))
            ->get();

        if ($years->isEmpty()) {
            $this->warn('No matching budget years found.');

            return self::SUCCESS;
        }

        foreach ($years as $budgetYear) {
            InvoiceTrackerSync::resyncYear($budgetYear);

            $source = $budgetYear->trackerSourceVersion?->name ?? '(no tracker-source version)';
            $this->info("{$budgetYear->year}: resynced from {$source}");
        }

        return self::SUCCESS;
    }
}
