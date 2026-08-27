<?php

use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Fortify\Features;
use Laravel\Fortify\TwoFactorAuthenticationProvider;
use PragmaRX\Google2FA\Google2FA;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());
});

function startTwoFactorChallenge(User $user): string
{
    return test()->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'phpunit',
    ])->json('challenge_id');
}

test('a valid totp code completes the challenge and issues a token', function () {
    $provider = app(TwoFactorAuthenticationProvider::class);
    $secret = $provider->generateSecretKey();

    $user = User::factory()->create([
        'two_factor_secret' => encrypt($secret),
        'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
        'two_factor_confirmed_at' => now(),
    ]);

    $challengeId = startTwoFactorChallenge($user);

    $response = $this->postJson('/api/v1/auth/two-factor-challenge', [
        'challenge_id' => $challengeId,
        'code' => app(Google2FA::class)->getCurrentOtp($secret),
    ]);

    $response->assertOk();
    $response->assertJsonStructure(['data' => ['token', 'user', 'expires_at']]);
});

test('an invalid totp code is rejected with the shared error envelope', function () {
    $secret = app(TwoFactorAuthenticationProvider::class)->generateSecretKey();

    $user = User::factory()->create([
        'two_factor_secret' => encrypt($secret),
        'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
        'two_factor_confirmed_at' => now(),
    ]);

    $challengeId = startTwoFactorChallenge($user);

    $response = $this->postJson('/api/v1/auth/two-factor-challenge', [
        'challenge_id' => $challengeId,
        'code' => '000000',
    ]);

    $response->assertStatus(422);
    $response->assertJson(['code' => 'VALIDATION_FAILED']);
});

test('a valid recovery code completes the challenge and is single use', function () {
    $user = User::factory()->withTwoFactor()->create();
    $challengeId = startTwoFactorChallenge($user);

    $response = $this->postJson('/api/v1/auth/two-factor-challenge', [
        'challenge_id' => $challengeId,
        'recovery_code' => 'recovery-code-1',
    ]);

    $response->assertOk();
    expect($user->fresh()->recoveryCodes())->not->toContain('recovery-code-1');
});

test('an expired or unknown challenge id is rejected', function () {
    $response = $this->postJson('/api/v1/auth/two-factor-challenge', [
        'challenge_id' => (string) Str::uuid(),
        'code' => '000000',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['challenge_id']);
});
