<?php

namespace App\Filament\Clusters;

use Filament\Clusters\Cluster;

/**
 * Invoice Tracker — the IT director's record of actually-approved invoices
 * per supplier/category/month, compared against planned amounts. Planned
 * amounts mirror in from the Budget Planner's tracker-source version (see
 * App\Services\InvoiceTrackerSync); actual invoices are entered only here.
 * Gated to view_invoices/manage_invoices — held solely by the
 * invoice_tracker role (super_admin-grantable only) + super_admin bypass.
 */
class InvoiceTracker extends Cluster
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-receipt-percent';
    protected static string | \UnitEnum | null $navigationGroup = 'IT Budget';
    protected static ?string $navigationLabel = 'Invoice Tracker';
    protected static ?int $navigationSort = 11;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view_invoices') ?? false;
    }
}
