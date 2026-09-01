<?php

use App\Models\Employee;
use App\Models\PersonalEvent;
use App\Models\User;

test('an employee sees only their own events', function () {
    $me = Employee::factory()->create();
    $someoneElse = Employee::factory()->create();

    PersonalEvent::factory()->count(2)->create(['employee_id' => $me->id]);
    PersonalEvent::factory()->create(['employee_id' => $someoneElse->id]);

    $response = $this->actingAs($me->user)->getJson('/api/v1/personal-events');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(2);
});

test('index can be filtered to a date window', function () {
    $me = Employee::factory()->create();
    PersonalEvent::factory()->create([
        'employee_id' => $me->id,
        'start_date' => '2026-01-05',
        'end_date' => '2026-01-06',
    ]);
    PersonalEvent::factory()->create([
        'employee_id' => $me->id,
        'start_date' => '2026-03-10',
        'end_date' => '2026-03-10',
    ]);

    $response = $this->actingAs($me->user)->getJson(
        '/api/v1/personal-events?filter[date_from]=2026-03-01&filter[date_to]=2026-03-31',
    );

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.start_date'))->toBe('2026-03-10');
});

test('any employee can create a single or multi-day event for themselves', function () {
    $me = Employee::factory()->create();

    $response = $this->actingAs($me->user)->postJson('/api/v1/personal-events', [
        'title' => 'Dentist',
        'start_date' => '2026-04-01',
        'end_date' => '2026-04-03',
        'description' => 'Root canal, ugh',
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.title', 'Dentist');
    $response->assertJsonPath('data.end_date', '2026-04-03');

    $this->assertDatabaseHas('personal_events', [
        'employee_id' => $me->id,
        'title' => 'Dentist',
    ]);
});

test('end date must not precede start date', function () {
    $me = Employee::factory()->create();

    $this->actingAs($me->user)->postJson('/api/v1/personal-events', [
        'title' => 'Backwards',
        'start_date' => '2026-04-10',
        'end_date' => '2026-04-01',
    ])->assertJsonValidationErrors('end_date');
});

test('a user with no employee record cannot create events', function () {
    $orphan = User::factory()->create();

    $this->actingAs($orphan)->postJson('/api/v1/personal-events', [
        'title' => 'Nope',
        'start_date' => '2026-04-01',
        'end_date' => '2026-04-01',
    ])->assertStatus(403);
});

test('an employee can update and delete their own event', function () {
    $me = Employee::factory()->create();
    $event = PersonalEvent::factory()->create([
        'employee_id' => $me->id,
        'title' => 'Old',
        'start_date' => '2026-05-01',
        'end_date' => '2026-05-01',
    ]);

    $this->actingAs($me->user)
        ->putJson("/api/v1/personal-events/{$event->id}", ['title' => 'New'])
        ->assertOk()
        ->assertJsonPath('data.title', 'New')
        ->assertJsonPath('data.start_date', '2026-05-01');

    $this->actingAs($me->user)
        ->deleteJson("/api/v1/personal-events/{$event->id}")
        ->assertNoContent();

    expect(PersonalEvent::query()->find($event->id))->toBeNull();
});

test("another employee's event is invisible — 404, not 403", function () {
    $mine = Employee::factory()->create();
    $theirs = PersonalEvent::factory()->create();

    $this->actingAs($mine->user)
        ->putJson("/api/v1/personal-events/{$theirs->id}", ['title' => 'hijack'])
        ->assertNotFound();

    $this->actingAs($mine->user)
        ->deleteJson("/api/v1/personal-events/{$theirs->id}")
        ->assertNotFound();

    expect($theirs->fresh()->title)->not->toBe('hijack');
});

test('personal events require authentication', function () {
    $this->getJson('/api/v1/personal-events')->assertUnauthorized();
});
