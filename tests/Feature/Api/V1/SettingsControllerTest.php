<?php

use App\Enums\PermissionName;
use App\Mail\TestMailMessage;
use App\Models\OrganizationSettings;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

function userWithSettingsPermission(PermissionName $permission): User
{
    $user = User::factory()->create();
    $role = Role::factory()->create();
    $perm = Permission::query()->firstOrCreate(['name' => $permission->value]);
    $role->permissions()->attach($perm);
    UserRole::factory()->create(['user_id' => $user->id, 'role_id' => $role->id]);

    return $user;
}

test('reading organization settings requires settings.manage', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->getJson('/api/v1/settings/organization')->assertStatus(403);
});

test('a user with settings.manage can read and update organization settings', function () {
    $user = userWithSettingsPermission(PermissionName::SettingsManage);

    $this->actingAs($user)->getJson('/api/v1/settings/organization')
        ->assertOk()
        ->assertJsonPath('data.timezone', 'Asia/Dhaka');

    $response = $this->actingAs($user)->putJson('/api/v1/settings/organization', [
        'timezone' => 'Asia/Karachi',
        'currency' => 'BDT',
        'weekend_days' => ['friday', 'saturday'],
    ]);

    $response->assertOk();
    $response->assertJsonPath('data.timezone', 'Asia/Karachi');
    $response->assertJsonPath('data.currency', 'BDT');
    $response->assertJsonPath('data.weekend_days', ['friday', 'saturday']);
});

test('setting default_weekend_day updates the single weekly off day and keeps weekend_days in sync', function () {
    $user = userWithSettingsPermission(PermissionName::SettingsManage);

    $response = $this->actingAs($user)->putJson('/api/v1/settings/organization', [
        'default_weekend_day' => 'sunday',
    ]);

    $response->assertOk();
    $response->assertJsonPath('data.default_weekend_day', 'sunday');
    $response->assertJsonPath('data.weekend_days', ['sunday']);
    expect(OrganizationSettings::current()->default_weekend_day->value)->toBe('sunday');
});

test('an invalid weekday is rejected for default_weekend_day', function () {
    $user = userWithSettingsPermission(PermissionName::SettingsManage);

    $this->actingAs($user)->putJson('/api/v1/settings/organization', [
        'default_weekend_day' => 'someday',
    ])->assertStatus(422);
});

test('organization settings expose the resolved reporting period', function () {
    $user = userWithSettingsPermission(PermissionName::SettingsManage);

    $this->actingAs($user)->getJson('/api/v1/settings/organization')
        ->assertOk()
        ->assertJsonPath('data.reporting_month_cutoff_day', null)
        ->assertJsonStructure(['data' => ['reporting_period' => ['key', 'label', 'start_date', 'end_date']]]);
});

test('an admin can set the reporting month cutoff day and it shifts the resolved period', function () {
    $user = userWithSettingsPermission(PermissionName::SettingsManage);

    $this->travelTo('2026-09-10');

    $response = $this->actingAs($user)->putJson('/api/v1/settings/organization', [
        'reporting_month_cutoff_day' => 25,
    ]);

    $response->assertOk();
    $response->assertJsonPath('data.reporting_month_cutoff_day', 25);
    $response->assertJsonPath('data.reporting_period.start_date', '2026-08-26');
    $response->assertJsonPath('data.reporting_period.end_date', '2026-09-25');
    $response->assertJsonPath('data.reporting_period.label', 'September 2026');

    expect(OrganizationSettings::current()->reporting_month_cutoff_day)->toBe(25);
});

test('a non-admin cannot change the reporting month cutoff day', function () {
    $this->actingAs(User::factory()->create())
        ->putJson('/api/v1/settings/organization', ['reporting_month_cutoff_day' => 25])
        ->assertStatus(403);

    expect(OrganizationSettings::current()->reporting_month_cutoff_day)->toBeNull();
});

test('the reporting month cutoff day must be between 1 and 28', function () {
    $user = userWithSettingsPermission(PermissionName::SettingsManage);

    foreach ([0, 29, 31] as $invalid) {
        $this->actingAs($user)->putJson('/api/v1/settings/organization', [
            'reporting_month_cutoff_day' => $invalid,
        ])->assertStatus(422)->assertJsonValidationErrorFor('reporting_month_cutoff_day');
    }
});

test('clearing the reporting month cutoff day returns to calendar months', function () {
    $user = userWithSettingsPermission(PermissionName::SettingsManage);
    OrganizationSettings::current()->update(['reporting_month_cutoff_day' => 25]);

    $this->travelTo('2026-09-10');

    $response = $this->actingAs($user)->putJson('/api/v1/settings/organization', [
        'reporting_month_cutoff_day' => null,
    ]);

    $response->assertOk();
    $response->assertJsonPath('data.reporting_month_cutoff_day', null);
    $response->assertJsonPath('data.reporting_period.start_date', '2026-09-01');
    $response->assertJsonPath('data.reporting_period.end_date', '2026-09-30');
});

test('attendance.settings.manage is a distinct permission from settings.manage', function () {
    $user = userWithSettingsPermission(PermissionName::SettingsManage);

    $this->actingAs($user)->getJson('/api/v1/settings/attendance')->assertStatus(403);
});

test('a user with attendance.settings.manage can update the late grace period', function () {
    $user = userWithSettingsPermission(PermissionName::AttendanceSettingsManage);

    $response = $this->actingAs($user)->putJson('/api/v1/settings/attendance', ['late_grace_minutes' => 15]);

    $response->assertOk();
    $response->assertJsonPath('data.late_grace_minutes', 15);
    expect(OrganizationSettings::current()->late_grace_minutes)->toBe(15);
});

test('late_grace_minutes must be between 0 and 120', function () {
    $user = userWithSettingsPermission(PermissionName::AttendanceSettingsManage);

    $this->actingAs($user)->putJson('/api/v1/settings/attendance', ['late_grace_minutes' => 500])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['late_grace_minutes']);
});

test('hourly overtime is disabled by default (§47)', function () {
    $user = userWithSettingsPermission(PermissionName::OvertimePolicyManage);

    $this->actingAs($user)->getJson('/api/v1/settings/overtime')
        ->assertOk()
        ->assertJsonPath('data.hourly_overtime_enabled', false)
        ->assertJsonPath('data.overtime_enabled', true)
        ->assertJsonPath('data.weekend_overtime_enabled', true)
        ->assertJsonPath('data.holiday_overtime_enabled', true);
});

test('a user with overtime.policy.manage can update overtime settings, including enabling hourly overtime', function () {
    $user = userWithSettingsPermission(PermissionName::OvertimePolicyManage);

    $response = $this->actingAs($user)->putJson('/api/v1/settings/overtime', [
        'hourly_overtime_enabled' => true,
        'overtime_hourly_rate_mode' => 'FIXED',
        'overtime_hourly_fixed_rate' => '250.5000',
        'overtime_full_day_minutes' => 420,
    ]);

    $response->assertOk();
    $response->assertJsonPath('data.hourly_overtime_enabled', true);
    $response->assertJsonPath('data.overtime_hourly_rate_mode', 'FIXED');
    $response->assertJsonPath('data.overtime_full_day_minutes', 420);
});

test('a user with payroll.settings.manage can update the payroll cutoff day', function () {
    $user = userWithSettingsPermission(PermissionName::PayrollSettingsManage);

    $response = $this->actingAs($user)->putJson('/api/v1/settings/payroll', ['payroll_cutoff_day' => 25]);

    $response->assertOk();
    $response->assertJsonPath('data.payroll_cutoff_day', 25);
});

test('branding reads and updates require settings.manage', function () {
    $this->actingAs(User::factory()->create())->getJson('/api/v1/settings/branding')->assertStatus(403);
});

test('an admin can set the app title and upload a logo', function () {
    Storage::fake('local');
    $user = userWithSettingsPermission(PermissionName::SettingsManage);

    $response = $this->actingAs($user)->post('/api/v1/settings/branding', [
        'company_name' => 'Northwind',
        'app_title' => 'Northwind People',
        'logo' => UploadedFile::fake()->image('logo.png', 200, 60),
    ]);

    $response->assertOk();
    $response->assertJsonPath('data.company_name', 'Northwind');
    $response->assertJsonPath('data.app_title', 'Northwind People');
    expect($response->json('data.logo_url'))->toContain('/branding/logo');

    $settings = OrganizationSettings::current();
    expect($settings->company_logo_path)->not->toBeNull();
    Storage::disk('local')->assertExists($settings->company_logo_path);
});

test('the public branding endpoint needs no session and falls back to the company name', function () {
    OrganizationSettings::current()->update(['company_name' => 'Acme', 'app_title' => null]);

    $this->getJson('/api/v1/branding')
        ->assertOk()
        ->assertJsonPath('company_name', 'Acme')
        ->assertJsonPath('app_title', 'Acme')
        ->assertJsonPath('logo_url', null);
});

test('mail settings never echo the password back, only whether one is stored', function () {
    $user = userWithSettingsPermission(PermissionName::SettingsManage);

    $this->actingAs($user)->putJson('/api/v1/settings/mail', [
        'mail_from_name' => 'Acme HR',
        'mail_from_address' => 'hr@acme.test',
        'mail_host' => 'smtp.acme.test',
        'mail_port' => 587,
        'mail_username' => 'postmaster',
        'mail_password' => 's3cret',
        'mail_encryption' => 'tls',
    ])->assertOk()
        ->assertJsonPath('data.mail_password_set', true)
        ->assertJsonPath('data.is_active', true)
        ->assertJsonMissingPath('data.mail_password');

    expect(OrganizationSettings::current()->mail_password)->toBe('s3cret');
});

test('omitting mail_password on a later update keeps the stored one', function () {
    $user = userWithSettingsPermission(PermissionName::SettingsManage);
    OrganizationSettings::current()->update(['mail_password' => 'keep-me', 'mail_host' => 'smtp.x', 'mail_from_address' => 'a@x.test']);

    $this->actingAs($user)->putJson('/api/v1/settings/mail', ['mail_from_name' => 'Renamed'])->assertOk();

    expect(OrganizationSettings::current()->mail_password)->toBe('keep-me');
});

test('the test-email endpoint sends a message and reports the recipient', function () {
    Mail::fake();
    $user = userWithSettingsPermission(PermissionName::SettingsManage);

    $this->actingAs($user)->postJson('/api/v1/settings/mail/test', ['to' => 'admin@acme.test'])
        ->assertOk()
        ->assertJsonPath('data.sent_to', 'admin@acme.test');

    Mail::assertSent(TestMailMessage::class, fn ($mail) => $mail->hasTo('admin@acme.test'));
});

test('the test-email endpoint validates the recipient address', function () {
    $user = userWithSettingsPermission(PermissionName::SettingsManage);

    $this->actingAs($user)->postJson('/api/v1/settings/mail/test', ['to' => 'not-an-email'])
        ->assertStatus(422);
});
