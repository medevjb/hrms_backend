<?php

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\ScheduledTaskRun;
use Inertia\Inertia;

/**
 * docs/PRD.md §79 — the console's page controllers and the props they expose.
 */
function seedConsoleLog(string $contents): void
{
    $path = tempnam(sys_get_temp_dir(), 'consolelog_').'.log';
    file_put_contents($path, $contents);
    config(['system-console.logs.channel_path' => $path]);
}

/**
 * Fetch a deferred Inertia prop via the partial-reload request the client makes.
 *
 * @return array<string, mixed>
 */
function inertiaPartial(string $url, string $component, string $prop): array
{
    $response = test()->get($url, [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) Inertia::getVersion(),
        'X-Inertia-Partial-Component' => $component,
        'X-Inertia-Partial-Data' => $prop,
    ]);

    return $response->json('props');
}

beforeEach(fn () => $this->actingAs(systemConsoleUser()));

test('the overview page exposes the health snapshot', function () {
    $this->get('/system')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('system/overview', shouldExist: false)
            ->has('health.database')
            ->has('health.errors_24h')
            ->where('health.environment', 'testing'));
});

test('the logs page echoes the filters and defers the entry page', function () {
    seedConsoleLog(implode("\n", [
        '[2026-08-31 09:00:00] local.INFO: a quiet note',
        '[2026-08-31 09:01:00] local.ERROR: the thing that broke',
        '',
    ]));

    $this->get('/system/logs?level=ERROR&search=broke')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('system/logs', shouldExist: false)
            ->where('filters.level', 'ERROR')
            ->where('filters.search', 'broke')
            ->has('levels'));

    $props = inertiaPartial('/system/logs?level=ERROR&search=broke', 'system/logs', 'result');

    expect($props['result']['entries'])->toHaveCount(1);
    expect($props['result']['entries'][0]['message'])->toBe('the thing that broke');
});

test('the schedule page lists registered commands with their last run', function () {
    ScheduledTaskRun::factory()->failed()->create([
        'command' => 'attendance:close',
        'started_at' => now()->subHours(2),
    ]);

    $this->get('/system/schedule')
        ->assertOk()
        ->assertInertia(function ($page) {
            $page->component('system/schedule', shouldExist: false);

            $commands = collect($page->toArray()['props']['commands']);
            $attendance = $commands->firstWhere('command', 'attendance:close');

            expect($attendance)->not->toBeNull();
            expect($attendance['expression'])->toBe('0 2 * * *');
            expect($attendance['last_run']['status'])->toBe('failed');
        });
});

test('a command that has never run still appears with no last run', function () {
    $this->get('/system/schedule')
        ->assertInertia(function ($page) {
            $commands = collect($page->toArray()['props']['commands']);
            $rollover = $commands->firstWhere('command', 'leave:rollover');

            expect($rollover['last_run'])->toBeNull();
        });
});

test('the schedule history page paginates a command run history', function () {
    ScheduledTaskRun::factory()->count(3)->succeeded()->create(['command' => 'leave:rollover']);

    $this->get('/system/schedule/leave:rollover')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('system/schedule-history', shouldExist: false)
            ->where('history.command', 'leave:rollover')
            ->where('history.is_registered', true)
            ->where('history.meta.total', 3));
});

test('the audit page lists entries and filters by action', function () {
    AuditLog::factory()->create(['action' => AuditAction::SalaryChanged]);
    AuditLog::factory()->create(['action' => AuditAction::LeaveApproved]);

    $this->get('/system/audit')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('system/audit', shouldExist: false)
            ->where('logs.meta.total', 2)
            ->has('actions'));

    $this->get('/system/audit?action=SALARY_CHANGED')
        ->assertInertia(fn ($page) => $page->where('logs.meta.total', 1));
});

test('the audit page offers no write path', function () {
    // Every audit route is a GET; there is no create/update/delete endpoint.
    $auditRoutes = collect(app('router')->getRoutes())
        ->filter(fn ($r) => str_starts_with($r->uri(), 'system/audit'));

    expect($auditRoutes)->not->toBeEmpty();
    $auditRoutes->each(fn ($r) => expect($r->methods())->toContain('GET')->not->toContain('POST'));
});
