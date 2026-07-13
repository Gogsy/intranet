<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Prune activity-log rows older than config('activitylog.delete_records_older_than_days').
Schedule::command('activitylog:clean')->daily();

// Invoice Tracker: mail the owners a list of expected-monthly suppliers with
// no invoice entry for the month that just ended.
Schedule::command('invoices:check-missing')->monthlyOn(1, '08:00');
