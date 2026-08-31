<?php

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Services\QueueInspector;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * docs/PRD.md §79 — database-queue depth, failed-job inspection, and the
 * retry / forget operator actions.
 */
function seedFailedJob(array $overrides = []): string
{
    $uuid = $overrides['uuid'] ?? (string) Str::uuid();

    DB::table('failed_jobs')->insert(array_merge([
        'uuid' => $uuid,
        'connection' => 'database',
        'queue' => 'default',
        'payload' => json_encode([
            'uuid' => $uuid,
            'displayName' => 'App\\Jobs\\SendReport',
            'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
            'maxTries' => null,
            'data' => ['commandName' => 'App\\Jobs\\SendReport'],
        ]),
        'exception' => "RuntimeException: boom\n#0 /app/x.php(10): foo()\n#1 {main}",
        'failed_at' => now(),
    ], $overrides));

    return $uuid;
}

beforeEach(function () {
    config(['queue.default' => 'database']);
});

test('depth reports pending jobs, per-queue counts and oldest age', function () {
    Carbon::setTestNow('2026-08-31 12:00:00');

    DB::table('jobs')->insert([
        ['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'available_at' => 0, 'created_at' => now()->subMinutes(10)->getTimestamp()],
        ['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'available_at' => 0, 'created_at' => now()->getTimestamp()],
        ['queue' => 'mail', 'payload' => '{}', 'attempts' => 0, 'available_at' => 0, 'created_at' => now()->getTimestamp()],
    ]);

    $depth = app(QueueInspector::class)->depth();

    expect($depth['available'])->toBeTrue()
        ->and($depth['total_pending'])->toBe(3)
        ->and($depth['by_queue'])->toBe(['default' => 2, 'mail' => 1])
        ->and($depth['oldest_pending_age_seconds'])->toBe(600);

    Carbon::setTestNow();
});

test('depth reports unavailable for a non-database connection', function () {
    config(['queue.default' => 'sync']);

    expect(app(QueueInspector::class)->depth())->toBe(['connection' => 'sync', 'available' => false]);
});

test('failed jobs are listed newest-first with a summary and full exception', function () {
    seedFailedJob(['failed_at' => now()->subHour()]);
    $recent = seedFailedJob(['failed_at' => now()]);

    $result = app(QueueInspector::class)->failedJobs(1, 20);

    expect($result['meta']['total'])->toBe(2)
        ->and($result['data'][0]['uuid'])->toBe($recent)
        ->and($result['data'][0]['exception_summary'])->toBe('RuntimeException: boom')
        ->and($result['data'][0]['exception'])->toContain('#1 {main}');
});

test('retry re-queues the job and drops it from the failed list', function () {
    $uuid = seedFailedJob();

    $result = app(QueueInspector::class)->retry($uuid);

    expect($result['status'])->toBe('ok')
        ->and(DB::table('failed_jobs')->where('uuid', $uuid)->exists())->toBeFalse()
        ->and(DB::table('jobs')->count())->toBe(1);
});

test('retry all empties the failed list', function () {
    seedFailedJob();
    seedFailedJob();

    $result = app(QueueInspector::class)->retryAll();

    expect($result['status'])->toBe('ok')
        ->and(DB::table('failed_jobs')->count())->toBe(0);
});

test('forget removes the job without re-queuing it', function () {
    $uuid = seedFailedJob();

    $result = app(QueueInspector::class)->forget($uuid);

    expect($result['status'])->toBe('ok')
        ->and(DB::table('failed_jobs')->where('uuid', $uuid)->exists())->toBeFalse()
        ->and(DB::table('jobs')->count())->toBe(0);
});

test('an unknown uuid is a no-op not-found for both actions', function () {
    seedFailedJob();

    expect(app(QueueInspector::class)->retry('missing')['status'])->toBe('not_found')
        ->and(app(QueueInspector::class)->forget('missing')['status'])->toBe('not_found')
        ->and(DB::table('failed_jobs')->count())->toBe(1);
});

test('the queue page renders depth and defers the failed list', function () {
    $this->actingAs(systemConsoleUser());

    $this->get('/system/queue')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('system/queue', shouldExist: false)
            ->where('depth.available', true));
});

test('the queue action routes sit in the CSRF-protected web group', function () {
    // Laravel skips CSRF validation under runningUnitTests(), so the protection
    // is asserted structurally: the route carries the `web` middleware group,
    // which includes ValidateCsrfToken.
    $route = collect(app('router')->getRoutes())
        ->first(fn ($r) => $r->getName() === 'system.queue.failed.retry');

    expect($route->gatherMiddleware())->toContain('web');
});

test('retrying a failed job from the console writes an audit entry', function () {
    $uuid = seedFailedJob();

    $this->actingAs(systemConsoleUser())
        ->post("/system/queue/failed/{$uuid}/retry")
        ->assertRedirect();

    expect(AuditLog::query()->where('action', AuditAction::QueueJobRetried)->count())->toBe(1);
});

test('forgetting a failed job from the console writes an audit entry', function () {
    $uuid = seedFailedJob();

    $this->actingAs(systemConsoleUser())
        ->post("/system/queue/failed/{$uuid}/forget")
        ->assertRedirect();

    $log = AuditLog::query()->where('action', AuditAction::QueueJobForgotten)->sole();
    expect($log->reason)->toContain($uuid);
});
