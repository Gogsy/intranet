<?php

namespace App\Console\Commands;

use App\Mail\MissingInvoicesAlert;
use App\Models\Supplier;
use App\Models\User;
use App\Support\InvoiceTracker\Months;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class CheckMissingInvoices extends Command
{
    protected $signature = 'invoices:check-missing {--year=} {--month=}';

    protected $description = 'Email an alert listing expected-monthly suppliers without an invoice entry for the given month (defaults to the previous month)';

    public function handle(): int
    {
        $previousMonth = now()->subMonthNoOverflow();
        $year = (int) ($this->option('year') ?: $previousMonth->year);
        $month = (int) ($this->option('month') ?: $previousMonth->month);

        if ($month < 1 || $month > 12) {
            $this->error('Month must be between 1 and 12.');

            return self::FAILURE;
        }

        $missing = Supplier::query()
            ->expectedMonthly()
            ->whereDoesntHave('invoices', fn ($q) => $q->forMonth($year, $month))
            ->orderBy('name')
            ->get();

        $label = Months::name($month)." {$year}";

        if ($missing->isEmpty()) {
            $this->info("All expected suppliers have entries for {$label}. Nothing to send.");

            return self::SUCCESS;
        }

        // Only Invoice Tracker owners get the alert (explicit permission via
        // roles — super_admins must hold the invoice_tracker role to be mailed).
        $recipients = User::permission('manage_invoices')->pluck('email');

        foreach ($recipients as $email) {
            Mail::to($email)->send(new MissingInvoicesAlert($missing, $year, $month));
        }

        $this->info("Alert sent to {$recipients->count()} recipient(s): {$missing->count()} supplier(s) missing for {$label}.");

        return self::SUCCESS;
    }
}
