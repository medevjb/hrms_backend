<?php

use App\Models\User;

/**
 * docs/PRD.md §79 — one authorization boundary for the whole /system console:
 * a verified session holding `system.health.view`.
 */
test('an unauthenticated visitor is redirected to login', function () {
    $this->get('/system')->assertRedirect(route('login'));
    $this->get('/system/logs')->assertRedirect(route('login'));
});

test('a signed-in user without system.health.view is forbidden', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/system')->assertForbidden();
    $this->get('/system/logs')->assertForbidden();
    $this->get('/system/queue')->assertForbidden();
    $this->get('/system/schedule')->assertForbidden();
    $this->get('/system/audit')->assertForbidden();
});

test('a user holding system.health.view reaches the console', function () {
    $this->actingAs(systemConsoleUser());

    $this->get('/system')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('system/overview', shouldExist: false));
});
