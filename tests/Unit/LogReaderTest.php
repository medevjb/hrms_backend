<?php

use App\Services\LogReader;
use App\Support\Logs\LogQuery;
use Illuminate\Support\Carbon;

/**
 * docs/PRD.md §79 — parsing storage/logs into structured, filterable entries.
 */
function writeLog(string $contents): string
{
    $path = tempnam(sys_get_temp_dir(), 'logreader_').'.log';
    file_put_contents($path, $contents);

    return $path;
}

function reader(string $contents, int $maxScanBytes = 52428800): LogReader
{
    return new LogReader(writeLog($contents), $maxScanBytes);
}

it('parses a structured entry with its stack trace', function () {
    $log = <<<'LOG'
    [2026-08-31 09:00:00] local.ERROR: Something broke {"exception":"[object] (RuntimeException(code: 0): Something broke at /app/x.php:10)
    [stacktrace]
    #0 /app/y.php(20): foo()
    #1 {main}
    "}
    LOG;

    $page = reader($log)->query(new LogQuery);

    expect($page->entries)->toHaveCount(1);
    expect($page->entries[0]->level)->toBe('ERROR');
    expect($page->entries[0]->message)->toBe('Something broke {"exception":"[object] (RuntimeException(code: 0): Something broke at /app/x.php:10)');
    expect($page->entries[0]->trace)->toContain('#0 /app/y.php(20): foo()');
    expect($page->entries[0]->raw)->toBeFalse();
});

it('keeps a multi-line message attached to one entry', function () {
    $log = "[2026-08-31 09:00:00] local.INFO: line one\nline two\nline three\n[2026-08-31 09:01:00] local.INFO: next\n";

    $page = reader($log)->query(new LogQuery);

    expect($page->entries)->toHaveCount(2);
    // newest first
    expect($page->entries[0]->message)->toBe('next');
    expect($page->entries[1]->trace)->toBe("line two\nline three");
});

it('surfaces an unparseable line as a raw entry instead of dropping it', function () {
    $log = "not a log line at all\n[2026-08-31 09:00:00] local.INFO: real\n";

    $page = reader($log)->query(new LogQuery);

    expect($page->entries)->toHaveCount(2);
    $raw = collect($page->entries)->firstWhere('raw', true);
    expect($raw)->not->toBeNull();
    expect($raw->message)->toBe('not a log line at all');
});

it('returns an empty result when the log file is missing', function () {
    $page = (new LogReader('/no/such/file.log', 1000))->query(new LogQuery);

    expect($page->entries)->toBe([]);
    expect($page->truncated)->toBeFalse();
});

it('filters by minimum level', function () {
    $log = "[2026-08-31 09:00:00] local.DEBUG: d\n[2026-08-31 09:01:00] local.WARNING: w\n[2026-08-31 09:02:00] local.ERROR: e\n";

    $page = reader($log)->query(new LogQuery(minLevel: 'WARNING'));

    expect(collect($page->entries)->pluck('level')->sort()->values()->all())->toBe(['ERROR', 'WARNING']);
});

it('filters by time range and search together', function () {
    $log = implode("\n", [
        '[2026-08-31 08:00:00] local.ERROR: alpha problem',
        '[2026-08-31 09:00:00] local.ERROR: beta problem',
        '[2026-08-31 10:00:00] local.ERROR: alpha again',
        '',
    ]);

    $page = reader($log)->query(new LogQuery(
        from: Carbon::parse('2026-08-31 08:30:00'),
        to: Carbon::parse('2026-08-31 09:30:00'),
        search: 'beta',
    ));

    expect($page->entries)->toHaveCount(1);
    expect($page->entries[0]->message)->toBe('beta problem');
});

it('flags the result truncated when the scan cap is hit', function () {
    $lines = [];
    for ($i = 0; $i < 200; $i++) {
        $lines[] = "[2026-08-31 09:00:00] local.ERROR: entry number {$i}";
    }
    $log = implode("\n", $lines)."\n";

    // Cap well under the file size so the scan starts mid-file.
    $page = reader($log, 500)->query(new LogQuery);

    expect($page->truncated)->toBeTrue();
    expect(count($page->entries))->toBeGreaterThan(0);
});

it('paginates, with older matches on the next page', function () {
    $lines = [];
    for ($i = 0; $i < 5; $i++) {
        $lines[] = "[2026-08-31 09:0{$i}:00] local.ERROR: entry {$i}";
    }
    $log = implode("\n", $lines)."\n";
    $r = reader($log);

    $first = $r->query(new LogQuery(perPage: 2, page: 1));
    $second = $r->query(new LogQuery(perPage: 2, page: 2));

    expect($first->entries[0]->message)->toBe('entry 4');
    expect($first->hasMore)->toBeTrue();
    expect($second->entries[0]->message)->toBe('entry 2');
});

it('counts error-or-higher entries since a moment', function () {
    Carbon::setTestNow('2026-08-31 12:00:00');

    $log = implode("\n", [
        '[2026-08-31 09:00:00] local.ERROR: old error',
        '[2026-08-31 11:30:00] local.ERROR: recent error',
        '[2026-08-31 11:45:00] local.CRITICAL: recent critical',
        '[2026-08-31 11:50:00] local.WARNING: recent warning',
        '',
    ]);

    expect(reader($log)->errorCountSince(Carbon::parse('2026-08-31 11:00:00')))->toBe(2);

    Carbon::setTestNow();
});

it('groups a recurring error into one top-errors row', function () {
    $log = implode("\n", [
        '[2026-08-31 11:00:00] local.ERROR: User 12 not found',
        '[2026-08-31 11:05:00] local.ERROR: User 34 not found',
        '[2026-08-31 11:10:00] local.ERROR: User 56 not found',
        '[2026-08-31 11:15:00] local.ERROR: Disk full',
        '',
    ]);

    $top = reader($log)->topErrors(Carbon::parse('2026-08-31 00:00:00'));

    expect($top[0]['count'])->toBe(3);
    expect($top[0]['message'])->toBe('User {n} not found');
    expect($top[0]['last_seen'])->toContain('2026-08-31T11:10:00');
});
