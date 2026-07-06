<?php

namespace App\Filament\Clusters;

use Filament\Clusters\Cluster;

class BudgetPlanner extends Cluster
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-banknotes';
    protected static string | \UnitEnum | null $navigationGroup = 'IT Budget';
    protected static ?string $navigationLabel = 'IT Budget Planner';
    protected static ?int $navigationSort = 10;
}
