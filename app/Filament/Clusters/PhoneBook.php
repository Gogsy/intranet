<?php

namespace App\Filament\Clusters;

use Filament\Clusters\Cluster;

class PhoneBook extends Cluster
{
    protected static ?string $navigationIcon = 'heroicon-o-phone';
    protected static ?string $navigationGroup = 'Applications';
    protected static ?string $navigationLabel = 'Phone Book';
    protected static ?int $navigationSort = 40; // after App Downloads, Web Tools, Documentation Portal
}
