<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\BudgetPresenceController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ToolsController;
use App\Http\Controllers\AppsController;
use App\Http\Controllers\DocsController;
use App\Http\Controllers\ImenikController;
use App\Http\Controllers\InstallController;

/*
|--------------------------------------------------------------------------
| First-run install wizard
| Guarded by EnsureAppInstalled: reachable only until setup is completed.
|--------------------------------------------------------------------------
*/
Route::prefix('install')->name('install.')->group(function () {
    Route::get('/', [InstallController::class, 'index'])->name('index');

    Route::get('/admin', [InstallController::class, 'admin'])->name('admin');
    Route::post('/admin', [InstallController::class, 'storeAdmin'])->name('admin.store');

    Route::get('/smtp', [InstallController::class, 'smtp'])->name('smtp');
    Route::post('/smtp', [InstallController::class, 'storeSmtp'])->name('smtp.store');

    Route::get('/branding', [InstallController::class, 'branding'])->name('branding');
    Route::post('/branding', [InstallController::class, 'storeBranding'])->name('branding.store');
});

/*
|--------------------------------------------------------------------------
| Public / Core
|--------------------------------------------------------------------------
*/

// HOME -> Tools
Route::get('/', [ToolsController::class, 'index'])->name('home');

// Dashboard alias (npr. Breeze/Jetstream očekuju "dashboard")
Route::get('/dashboard', fn () => redirect()->route('home'))->name('dashboard');

// Tools & Apps
Route::get('/tools', [ToolsController::class, 'index'])->name('tools.index');
// Redirect-through link so clicks can be logged for the Tool Stats page.
Route::get('/tools/{tool}/go', [ToolsController::class, 'click'])->name('tools.click');
Route::get('/apps',  [AppsController::class, 'index'])->name('apps.index');
// Stable, permanent download link — always serves the active version.
// Throttled: in live-download mode this makes an outbound API call, so cap the
// per-IP request rate to blunt worker-exhaustion / abuse.
Route::get('/apps/{application}/download', [AppsController::class, 'download'])
    ->middleware('throttle:60,1')
    ->name('apps.download');

/*
|--------------------------------------------------------------------------
| Dokumentacija
| VAŽNO: /docs (index) mora biti iznad /docs/{slugPath}
|--------------------------------------------------------------------------
*/

// Index (grid root čvorova)
Route::get('/docs', [DocsController::class, 'index'])->name('docs.index');

// Show (kategorija + dokumenti) — jedan segment
Route::get('/docs/{slug}', [DocsController::class, 'show'])
    ->name('docs.show');

/*
|--------------------------------------------------------------------------
| Imenik (phone book directory) — public, role-aware visibility
|--------------------------------------------------------------------------
*/
Route::get('/imenik', [ImenikController::class, 'index'])->name('imenik.index');
Route::get('/imenik/export', [ImenikController::class, 'export'])->name('imenik.export');

/*
|--------------------------------------------------------------------------
| Profil (auth)
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Budget Planner live-presence heartbeat — deliberately a plain JSON route
    // (not a Livewire call) so it never re-renders the planner grid's chrome.
    // The grids POST here every ~3s from planner-tools.blade.php.
    Route::post('/budget/presence/{version}', [BudgetPresenceController::class, 'update'])
        ->name('budget.presence');
});

/*
|--------------------------------------------------------------------------
| Auth (Breeze)
|--------------------------------------------------------------------------
*/
require __DIR__.'/auth.php';
