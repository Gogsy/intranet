<?php

namespace Tests;

use App\Models\AppSetting;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    /**
     * EnsureAppInstalled redirects every request to /install until
     * AppSetting::setup_completed_at is set — mark the app installed for
     * every test so Feature tests exercise normal routes, not the wizard.
     */
    protected function setUp(): void
    {
        parent::setUp();

        if (Schema::hasTable('app_settings')) {
            AppSetting::forgetCurrent();
            AppSetting::query()->firstOrCreate([], ['setup_completed_at' => now()]);
        }
    }
}
