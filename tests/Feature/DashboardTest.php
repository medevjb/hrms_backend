<?php

use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('a signed-in user without system.health.view cannot visit the console', function () {
    $this->actingAs(User::factory()->create());

    // docs/PRD.md §79 — the /system console is gated on `system.health.view`.
    $this->get(route('dashboard'))->assertForbidden();
});

test('a user holding system.health.view can visit the console', function () {
    $this->actingAs(systemConsoleUser());

    $this->get(route('dashboard'))->assertOk();
});
