<?php

use App\Enums\PermissionName;
use App\Enums\Scope;
use App\Models\Document;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * docs/PRD.md §82 — employee documents in private storage, served only
 * through the authorised stream.
 */
function docUser(array $permissions, Scope $scope = Scope::AllEmployees): User
{
    $user = User::factory()->create();
    $role = Role::query()->firstOrCreate(['name' => 'Doc '.fake()->unique()->word()]);

    foreach ($permissions as $permission) {
        $perm = Permission::query()->firstOrCreate(['name' => $permission]);
        $role->permissions()->syncWithoutDetaching([$perm->id]);
    }

    UserRole::factory()->create(['user_id' => $user->id, 'role_id' => $role->id, 'scope' => $scope]);

    return $user;
}

beforeEach(fn () => Storage::fake('local'));

test('uploading a document requires document.manage', function () {
    $employee = Employee::factory()->create();

    $this->actingAs(docUser([PermissionName::DocumentView->value]))
        ->postJson("/api/v1/employees/{$employee->id}/documents", [
            'title' => 'Contract', 'category' => 'CONTRACT',
            'file' => UploadedFile::fake()->create('contract.pdf', 20, 'application/pdf'),
        ])->assertStatus(403);

    $response = $this->actingAs(docUser([PermissionName::DocumentManage->value]))
        ->post("/api/v1/employees/{$employee->id}/documents", [
            'title' => 'Contract', 'category' => 'CONTRACT',
            'file' => UploadedFile::fake()->create('contract.pdf', 20, 'application/pdf'),
        ]);

    $response->assertStatus(201)->assertJsonPath('data.title', 'Contract');

    $document = Document::query()->sole();
    Storage::disk('local')->assertExists($document->file_path);
});

test('an employee can list and download their own documents but not others', function () {
    $mine = Employee::factory()->create();
    $mineDoc = Document::factory()->create(['employee_id' => $mine->id, 'file_path' => 'employee-documents/mine.pdf']);
    Storage::disk('local')->put('employee-documents/mine.pdf', 'pdf-bytes');

    $other = Employee::factory()->create();
    $otherDoc = Document::factory()->create(['employee_id' => $other->id]);

    $this->actingAs($mine->user)->getJson("/api/v1/employees/{$mine->id}/documents")
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->actingAs($mine->user)->getJson("/api/v1/employees/{$other->id}/documents")->assertStatus(404);
    $this->actingAs($mine->user)->get("/api/v1/documents/{$mineDoc->id}/download")->assertOk();
    $this->actingAs($mine->user)->get("/api/v1/documents/{$otherDoc->id}/download")->assertStatus(404);
});

test('an employee can preview their own document inline but not someone elses', function () {
    $mine = Employee::factory()->create();
    $mineDoc = Document::factory()->create([
        'employee_id' => $mine->id,
        'file_path' => 'employee-documents/mine.pdf',
        'mime_type' => 'application/pdf',
    ]);
    Storage::disk('local')->put('employee-documents/mine.pdf', 'pdf-bytes');

    $otherDoc = Document::factory()->create(['employee_id' => Employee::factory()->create()->id]);

    $response = $this->actingAs($mine->user)->get("/api/v1/documents/{$mineDoc->id}/preview");
    $response->assertOk();
    expect($response->headers->get('Content-Disposition'))->toContain('inline');

    $this->actingAs($mine->user)->get("/api/v1/documents/{$otherDoc->id}/preview")->assertStatus(404);
});

test('deleting a document requires document.manage and removes the file', function () {
    $employee = Employee::factory()->create();
    Storage::disk('local')->put('employee-documents/x.pdf', 'bytes');
    $document = Document::factory()->create(['employee_id' => $employee->id, 'file_path' => 'employee-documents/x.pdf']);

    $this->actingAs($employee->user)->deleteJson("/api/v1/documents/{$document->id}")->assertStatus(403);

    $this->actingAs(docUser([PermissionName::DocumentManage->value]))
        ->deleteJson("/api/v1/documents/{$document->id}")
        ->assertNoContent();

    expect(Document::query()->find($document->id))->toBeNull();
    Storage::disk('local')->assertMissing('employee-documents/x.pdf');
});
