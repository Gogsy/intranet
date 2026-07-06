<?php

namespace App\Filament\Clusters;

use Filament\Clusters\Cluster;

class PhoneBook extends Cluster
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-phone';
    protected static string | \UnitEnum | null $navigationGroup = 'Applications';
    protected static ?string $navigationLabel = 'Phone Book';
    protected static ?int $navigationSort = 40; // after App Downloads, Web Tools, Documentation Portal
}
