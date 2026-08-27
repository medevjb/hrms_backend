<?php

use App\Models\User;
use Laravel\Fortify\Features;
use Laravel\Sanctum\PersonalAccessToken;

test('users can authenticate and receive a sanctum token', function () {
    $user = User::factory()->create();

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'phpunit',
    ]);

    $response->assertOk();
    $response->assertJsonStructure(['data' => ['token', 'user' => ['id', 'email'], 'expires_at']]);
    expect($response->json('data.user.email'))->toBe($user->email);
});

test('login fails with invalid credentials using the shared error envelope', function () {
    $user = User::factory()->create();

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
        'device_name' => 'phpunit',
    ]);

    $response->assertStatus(422);
    $response->assertJson(['code' => 'VALIDATION_FAILED']);
    $response->assertJsonValidationErrors(['email']);
});

test('login requires a device name', function () {
    $user = User::factory()->create();

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['device_name']);
});

test('users with two factor enabled receive a challenge instead of a token', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    $user = User::factory()->withTwoFactor()->create();

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'phpunit',
    ]);

    $response->assertStatus(202);
    $response->assertJson(['two_factor' => true]);
    $response->assertJsonStructure(['challenge_id']);
});

test('protected routes reject requests without a token using the shared error envelope', function () {
    $response = $this->getJson('/api/v1/auth/me');

    $response->assertStatus(401);
    $response->assertJson(['code' => 'UNAUTHENTICATED']);
});

test('authenticated users can fetch their own profile', function () {
    $user = User::factory()->create();
    $token = $user->createToken('phpunit')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/auth/me');

    $response->assertOk();
    $response->assertJson(['data' => ['id' => $user->id, 'email' => $user->email]]);
});

test('logout revokes only the current token', function () {
    $user = User::factory()->create();
    $tokenA = $user->createToken('device-a')->plainTextToken;
    $tokenB = $user->createToken('device-b')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$tokenA}")
        ->postJson('/api/v1/auth/logout');

    $response->assertNoContent();

    expect(PersonalAccessToken::findToken(explode('|', $tokenA)[1]))->toBeNull();
    expect(PersonalAccessToken::findToken(explode('|', $tokenB)[1]))->not->toBeNull();
});
