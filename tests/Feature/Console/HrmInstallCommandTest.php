<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('it creates the first admin with the admin role at two scopes', function () {
    $this->artisan('hrm:install', ['--email' => 'admin@example.com', '--password' => 'a-long-enough-password'])
        ->assertSuccessful();

    $admin = User::where('email', 'admin@example.com')->firstOrFail();

    expect($admin->hasRole('Admin'))->toBeTrue();
    expect($admin->roleAssignments()->pluck('scope')->map->value->all())
        ->toEqualCanonicalizing(['ALL_EMPLOYEES', 'SYSTEM']);
    expect(Hash::check('a-long-enough-password', $admin->password))->toBeTrue();
});

test('it generates and prints a password when none is supplied', function () {
    $this->artisan('hrm:install', ['--email' => 'admin@example.com'])
        ->expectsOutputToContain('Generated password (shown once):')
        ->assertSuccessful();
});

test('it refuses to run when a user already exists', function () {
    User::factory()->create();

    $this->artisan('hrm:install', ['--email' => 'admin@example.com', '--password' => 'a-long-enough-password'])
        ->assertFailed();

    expect(User::where('email', 'admin@example.com')->exists())->toBeFalse();
});

test('it rejects a password that is too short', function () {
    $this->artisan('hrm:install', ['--email' => 'admin@example.com', '--password' => 'short'])
        ->assertFailed();

    expect(User::query()->exists())->toBeFalse();
});

test('it accepts an 8-character password, matching the app default test password', function () {
    $this->artisan('hrm:install', ['--email' => 'admin@example.com', '--password' => 'password'])
        ->assertSuccessful();

    expect(User::query()->exists())->toBeTrue();
});
