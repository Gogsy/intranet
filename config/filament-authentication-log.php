<?php

use App\Models\User;
use Tapp\FilamentAuthenticationLog\Resources\AuthenticationLogResource;

return [
    // 'user-resource' => \App\Filament\Resources\UserResource::class,
    'resources' => [
        // Registered by FilamentAuthenticationLogPlugin (AdminPanelProvider).
        // Access is gated by App\Policies\AuthenticationLogPolicy (view_security).
        'AutenticationLogResource' => AuthenticationLogResource::class,
    ],

    'authenticable-resources' => [
        User::class,
    ],

    'authenticatable' => [
        'field-to-display' => null,
        'resource-page' => 'edit',
    ],

    'navigation' => [
        'authentication-log' => [
            'register' => true,
            'sort' => 2,
            'icon' => 'heroicon-o-finger-print',
            'group' => 'Security',
        ],
    ],

    'sort' => [
        'column' => 'login_at',
        'direction' => 'desc',
    ],
];
