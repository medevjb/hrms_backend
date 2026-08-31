<?php

use App\Http\Controllers\System\AuditController;
use App\Http\Controllers\System\LogController;
use App\Http\Controllers\System\OverviewController;
use App\Http\Controllers\System\QueueController;
use App\Http\Controllers\System\ScheduleController;
use Illuminate\Support\Facades\Route;

// This Inertia app is the System Admin/DevOps console only (docs/PRD.md §5.1, §79).
// No HR feature is ever built here — Next.js owns every HR-facing page.
Route::redirect('/', '/system')->name('home');

// docs/PRD.md §79 — every page and data/action endpoint of the console sits
// behind one boundary: an authenticated, verified web session whose user holds
// `system.health.view`. PermissionServiceProvider's Gate::before resolves the
// ability string, so `can:` needs no policy.
Route::middleware(['auth', 'verified', 'can:system.health.view'])
    ->prefix('system')
    ->group(function () {
        // Named `dashboard` (not `system.dashboard`) — Fortify's home path and
        // the frontend's `@/routes` helper both expect that name.
        Route::get('/', [OverviewController::class, 'show'])->name('dashboard');

        Route::get('logs', [LogController::class, 'index'])->name('system.logs');

        Route::get('queue', [QueueController::class, 'index'])->name('system.queue');
        Route::post('queue/failed/retry-all', [QueueController::class, 'retryAll'])
            ->name('system.queue.failed.retry-all');
        Route::post('queue/failed/{uuid}/retry', [QueueController::class, 'retry'])
            ->name('system.queue.failed.retry');
        Route::post('queue/failed/{uuid}/forget', [QueueController::class, 'forget'])
            ->name('system.queue.failed.forget');

        Route::get('schedule', [ScheduleController::class, 'index'])->name('system.schedule');
        Route::get('schedule/{command}', [ScheduleController::class, 'show'])
            ->where('command', '.*')
            ->name('system.schedule.show');

        Route::get('audit', [AuditController::class, 'index'])->name('system.audit');
    });

require __DIR__.'/settings.php';
