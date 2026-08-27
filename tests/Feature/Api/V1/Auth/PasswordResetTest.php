<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

test('requesting a reset link for a real email queues the notification', function () {
    Notification::fake();
    $user = User::factory()->create();

    $response = $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email]);

    $response->assertOk();
    Notification::assertSentTo($user, ResetPassword::class);
});

test('requesting a reset link for an unknown email still returns a generic success', function () {
    Notification::fake();

    $response = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.com']);

    $response->assertOk();
    Notification::assertNothingSent();
});

test('the emailed reset url points at the frontend, not a laravel route', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, function (ResetPassword $notification) use ($user) {
        $mail = $notification->toMail($user);
        $url = $mail->actionUrl;

        expect($url)->toStartWith('http://localhost:3000/reset-password?token=');
        expect($url)->toContain('email=');

        return true;
    });
});

test('a valid token resets the password and revokes existing tokens', function () {
    $user = User::factory()->create();
    $oldToken = $user->createToken('old-device')->plainTextToken;
    $token = app('auth.password.broker')->createToken($user);

    $response = $this->postJson('/api/v1/auth/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'a-new-password',
        'password_confirmation' => 'a-new-password',
    ]);

    $response->assertOk();
    expect(Hash::check('a-new-password', $user->fresh()->password))->toBeTrue();
    expect($user->fresh()->tokens()->count())->toBe(0);
    expect(DB::table('personal_access_tokens')->where('name', 'old-device')->exists())->toBeFalse();
});

test('an invalid token is rejected', function () {
    $user = User::factory()->create();

    $response = $this->postJson('/api/v1/auth/reset-password', [
        'token' => 'not-a-real-token',
        'email' => $user->email,
        'password' => 'a-new-password',
        'password_confirmation' => 'a-new-password',
    ]);

    $response->assertStatus(422);
    $response->assertJson(['code' => 'VALIDATION_FAILED']);
});

test('mismatched password confirmation is rejected', function () {
    $user = User::factory()->create();
    $token = app('auth.password.broker')->createToken($user);

    $response = $this->postJson('/api/v1/auth/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'a-new-password',
        'password_confirmation' => 'does-not-match',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['password']);
});
