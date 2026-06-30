<?php

use App\Models\AppSetting;

if (! function_exists('branding')) {
    /**
     * Global accessor for the app's branding / white-label settings singleton.
     *
     * Usage: branding()->name, branding()->logoUrl, branding()->primary, ...
     */
    function branding(): AppSetting
    {
        return AppSetting::current();
    }
}
