<?php

use Illuminate\Support\Facades\Route;

// This Inertia app is the System Admin/DevOps console only (docs/PRD.md §5.1, §79).
// No HR feature is ever built here — Next.js owns every HR-facing page.
Route::redirect('/', '/system')->name('home');

Route::middleware(['auth', 'verified'])->prefix('system')->group(function () {
    // TODO(Phase 1): gate this behind the `system.health.view` permission once
    // the permission system exists (docs/PRD.md §11) — 'auth' + 'verified' is a
    // placeholder, not the real authorization boundary.
    Route::inertia('/', 'system/dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
