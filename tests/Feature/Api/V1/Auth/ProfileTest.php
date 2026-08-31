<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

function bearer(User $user): array
{
    return ['Authorization' => 'Bearer '.$user->createToken('phpunit')->plainTextToken];
}

test('GET auth/profile returns the caller name, email and employment context', function () {
    $user = User::factory()->create(['name' => 'Nadia Rahman']);
    $department = Department::factory()->create(['name' => 'Engineering']);
    $team = Team::factory()->create(['name' => 'Platform', 'department_id' => $department->id]);
    $employee = Employee::factory()->for($user)->create([
        'designation' => 'Backend Engineer',
        'phone' => '+8801000000',
    ]);
    TeamMember::factory()->create(['team_id' => $team->id, 'employee_id' => $employee->id]);

    $response = $this->withHeaders(bearer($user))->getJson('/api/v1/auth/profile');

    $response->assertOk()
        ->assertJsonPath('data.name', 'Nadia Rahman')
        ->assertJsonPath('data.email', $user->email)
        ->assertJsonPath('data.employee.designation', 'Backend Engineer')
        ->assertJsonPath('data.employee.phone', '+8801000000')
        ->assertJsonPath('data.employee.department.name', 'Engineering')
        ->assertJsonPath('data.employee.team.name', 'Platform');
});

test('GET auth/profile works for a user with no employee record', function () {
    $user = User::factory()->create();

    $this->withHeaders(bearer($user))->getJson('/api/v1/auth/profile')
        ->assertOk()
        ->assertJsonPath('data.employee', null);
});

test('PUT auth/profile updates the name and own contact fields', function () {
    $user = User::factory()->create(['name' => 'Old Name']);
    $employee = Employee::factory()->for($user)->create([
        'designation' => 'Recruiter',
        'phone' => null,
    ]);

    $response = $this->withHeaders(bearer($user))->putJson('/api/v1/auth/profile', [
        'name' => 'New Name',
        'phone' => '+8801999',
        'emergency_contact_name' => 'Kin',
        'designation' => 'CEO', // HR-controlled — must be ignored
    ]);

    $response->assertOk()->assertJsonPath('data.name', 'New Name');

    expect($user->fresh()->name)->toBe('New Name')
        ->and($employee->fresh()->phone)->toBe('+8801999')
        ->and($employee->fresh()->emergency_contact_name)->toBe('Kin')
        ->and($employee->fresh()->designation)->toBe('Recruiter');
});

test('PUT auth/profile requires a name', function () {
    $user = User::factory()->create();

    $this->withHeaders(bearer($user))->putJson('/api/v1/auth/profile', ['name' => ''])
        ->assertStatus(422)
        ->assertJsonValidationErrors('name');
});

test('PUT auth/password changes the password and revokes every token (§92.2)', function () {
    $user = User::factory()->create(['password' => Hash::make('password')]);
    $user->createToken('another-device');

    $response = $this->withHeaders(bearer($user))->putJson('/api/v1/auth/password', [
        'current_password' => 'password',
        'password' => 'a-brand-new-secret-1',
        'password_confirmation' => 'a-brand-new-secret-1',
    ]);

    $response->assertNoContent();

    expect(Hash::check('a-brand-new-secret-1', $user->fresh()->password))->toBeTrue();
    // Every token gone — the one this request used, and every other device.
    $this->assertDatabaseCount('personal_access_tokens', 0);
});

test('a user can upload, replace, serve and remove their own photo', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $employee = Employee::factory()->for($user)->create();

    $upload = $this->withHeaders(bearer($user))->postJson('/api/v1/auth/profile/photo', [
        'photo' => UploadedFile::fake()->image('me.jpg', 300, 300),
    ]);
    $upload->assertOk();
    expect($upload->json('data.photo_url'))->toStartWith('/auth/profile/photo');

    $firstPath = $employee->fresh()->profile_image_path;
    Storage::disk('local')->assertExists($firstPath);

    $this->withHeaders(bearer($user))->get('/api/v1/auth/profile/photo')->assertOk();

    // Replacing drops the previous file.
    $this->withHeaders(bearer($user))->postJson('/api/v1/auth/profile/photo', [
        'photo' => UploadedFile::fake()->image('new.png'),
    ])->assertOk();
    Storage::disk('local')->assertMissing($firstPath);

    $this->withHeaders(bearer($user))->deleteJson('/api/v1/auth/profile/photo')->assertOk();
    expect($employee->fresh()->profile_image_path)->toBeNull();
});

test('uploading a non-image is rejected', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    Employee::factory()->for($user)->create();

    $this->withHeaders(bearer($user))->postJson('/api/v1/auth/profile/photo', [
        'photo' => UploadedFile::fake()->create('resume.pdf', 20, 'application/pdf'),
    ])->assertStatus(422)->assertJsonValidationErrors('photo');
});

test('a user with no employee record cannot set a photo', function () {
    $user = User::factory()->create();

    $this->withHeaders(bearer($user))->postJson('/api/v1/auth/profile/photo', [
        'photo' => UploadedFile::fake()->image('me.jpg'),
    ])->assertStatus(409);
});

test('PUT auth/password rejects a wrong current password', function () {
    $user = User::factory()->create(['password' => Hash::make('password')]);

    $this->withHeaders(bearer($user))->putJson('/api/v1/auth/password', [
        'current_password' => 'not-the-password',
        'password' => 'a-brand-new-secret-1',
        'password_confirmation' => 'a-brand-new-secret-1',
    ])->assertStatus(422)->assertJsonValidationErrors('current_password');

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});
