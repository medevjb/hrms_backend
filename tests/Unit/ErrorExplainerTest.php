<?php

use App\Support\Logs\ErrorExplainer;

/**
 * docs/PRD.md §79 — the console turns raw log lines into one plain sentence a
 * non-technical reader can act on.
 */
it('explains common failures in plain language', function (string $message, string $expected) {
    expect(ErrorExplainer::explain($message))->toBe($expected);
})->with([
    'database unreachable' => [
        'SQLSTATE[HY000] [2002] Connection refused',
        'The app could not reach the database.',
    ],
    'missing driver' => [
        'could not find driver (SQL: select 1)',
        'The database driver is not installed on the server.',
    ],
    'missing table' => [
        "SQLSTATE[42S02]: Base table or view not found: 1146 Table 'hrms.widgets' doesn't exist",
        'A database table is missing — a database update has probably not been run.',
    ],
    'duplicate row' => [
        "SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry 'a@b.com' for key 'users_email_unique'",
        'A record could not be saved because another one with the same value already exists.',
    ],
    'page not found' => [
        'Symfony\Component\HttpKernel\Exception\NotFoundHttpException: GET /nope',
        'Someone opened a page or link that does not exist.',
    ],
    'unauthorized' => [
        'Illuminate\Auth\Access\AuthorizationException: This action is unauthorized.',
        'Someone tried to do something their account is not allowed to do.',
    ],
    'expired session' => [
        'Illuminate\Session\TokenMismatchException in VerifyCsrfToken.php',
        'A form was submitted after its session expired. Signing in again clears it.',
    ],
    'mail failure' => [
        'Symfony\Component\Mailer\Exception\TransportException: Connection could not be established with host smtp.example.com',
        'An email could not be sent — the mail server refused the connection or the login.',
    ],
    'outside service unreachable' => [
        'GuzzleHttp\Exception\ConnectException: cURL error 6: Could not resolve host: api.example.com',
        'The app could not reach an outside service it depends on.',
    ],
    'disk full' => [
        'fopen(/var/www/storage/logs/laravel.log): failed to open stream: No space left on device',
        'The server has run out of disk space.',
    ],
    'file permissions' => [
        'file_put_contents(/var/www/storage/framework/cache/x): Permission denied',
        'The app could not read or write a file it needed — usually a permissions problem on the server.',
    ],
    'out of memory' => [
        'Allowed memory size of 134217728 bytes exhausted (tried to allocate 20480 bytes)',
        'A task needed more memory than the server allows and was stopped.',
    ],
    'failed job' => [
        'App\Jobs\SyncPayroll has been attempted too many times or run too long.',
        'A background job kept failing and the app stopped retrying it.',
    ],
    'missing class' => [
        'Error: Class "App\Services\Missing" not found',
        'The app referred to code that is missing. This needs a developer.',
    ],
    'null bug' => [
        'Error: Call to a member function format() on null',
        'The app hit a bug: it used a value that was empty or the wrong type. This needs a developer.',
    ],
]);

it('still matches after the top-errors masker has run', function () {
    // LogReader::mask() replaces digits with {n} and quoted fragments with "{}".
    $masked = 'SQLSTATE[HY000] [{n}] Connection refused';

    expect(ErrorExplainer::explain($masked))->toBe('The app could not reach the database.');
});

it('returns null when no pattern matches', function () {
    expect(ErrorExplainer::explain('the flux capacitor stopped fluxing'))->toBeNull();
});

it('reads the stack trace as well as the message', function () {
    $explanation = ErrorExplainer::explain(
        'Something went wrong',
        '#0 /app/x.php: PDOException: SQLSTATE[HY000] [2002] Connection refused',
    );

    expect($explanation)->toBe('The app could not reach the database.');
});

it('falls back to a level-based generic', function (string $level, string $expected) {
    expect(ErrorExplainer::generic($level))->toBe($expected);
})->with([
    ['critical', 'The app hit a serious problem it could not recover from on its own.'],
    ['error', 'The app ran into an unexpected problem while handling a request.'],
    ['warning', 'Something unexpected happened, but the app kept working.'],
    ['info', 'An informational message from the app — no action needed.'],
]);
