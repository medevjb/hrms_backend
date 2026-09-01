<?php

namespace App\Support\Logs;

/**
 * docs/PRD.md §79 — turns a raw log message into one plain sentence that a
 * non-technical reader (an HR ops manager, an office IT lead) can act on.
 *
 * `explain()` returns null when the message matches no known shape; callers
 * fall back to `generic()`, which is keyed only off the severity level.
 */
class ErrorExplainer
{
    /**
     * Ordered most-specific first. Each pattern is matched against the message
     * plus its stack trace, case-insensitively.
     *
     * @var list<array{0: string, 1: string}>
     */
    private const PATTERNS = [
        // Database connectivity and schema.
        ['/could not find driver/i',
            'The database driver is not installed on the server.'],
        ['/SQLSTATE\[(HY000|08006|08001|08004)\].*(connection refused|could not connect|no such host|gone away|server has gone|timed out|Connection timed out)/i',
            'The app could not reach the database.'],
        ['/SQLSTATE\[42S02\]|Base table or view not found|no such table/i',
            'A database table is missing — a database update has probably not been run.'],
        ['/SQLSTATE\[42S22\]|Unknown column|no such column/i',
            'The database is missing a field the app expects — a database update is pending.'],
        ['/SQLSTATE\[23000\].*(Duplicate entry|UNIQUE constraint)/i',
            'A record could not be saved because another one with the same value already exists.'],
        ['/SQLSTATE\[23000\].*foreign key constraint/i',
            'A record could not be saved because it refers to something that no longer exists.'],
        ['/Deadlock found|Lock wait timeout/i',
            'Two changes hit the same data at once and one was rolled back. It usually succeeds on retry.'],
        ['/SQLSTATE\[23000\]/i',
            'A record could not be saved because it broke a database rule.'],
        // Routing, permissions, sessions.
        ['/NotFoundHttpException|Route \[[^\]]*\] not defined/i',
            'Someone opened a page or link that does not exist.'],
        ['/ModelNotFoundException|No query results for model/i',
            'The app looked for a record that is not there — often a stale link or a deleted item.'],
        ['/AuthorizationException|This action is unauthorized|AccessDeniedHttpException/i',
            'Someone tried to do something their account is not allowed to do.'],
        ['/TokenMismatchException|CSRF token mismatch|Page Expired/i',
            'A form was submitted after its session expired. Signing in again clears it.'],
        ['/ThrottleRequests|Too Many Attempts|429 Too Many Requests/i',
            'Requests came in faster than allowed and some were turned away.'],
        ['/MethodNotAllowedHttpException/i',
            'A request reached an address the wrong way — usually a broken link or integration.'],
        ['/ValidationException/i',
            'A submitted form did not pass validation.'],
        // Outside services.
        ['/Swift_TransportException|Symfony\\\\Component\\\\Mailer|Failed to authenticate on SMTP|Expected response code "?250|Connection could not be established with host/i',
            'An email could not be sent — the mail server refused the connection or the login.'],
        ['/cURL error|Could not resolve host|SSL certificate problem|GuzzleHttp\\\\Exception|ConnectException|Connection timed out/i',
            'The app could not reach an outside service it depends on.'],
        // Server resources and files.
        ['/No space left on device|disk[^.]*full/i',
            'The server has run out of disk space.'],
        ['/Permission denied|failed to open stream|Unable to write|could not be written to/i',
            'The app could not read or write a file it needed — usually a permissions problem on the server.'],
        ['/Allowed memory size|Out of memory/i',
            'A task needed more memory than the server allows and was stopped.'],
        ['/Maximum execution time|Maximum function nesting level/i',
            'A task ran too long and was stopped before it finished.'],
        // Background jobs.
        ['/has been attempted too many times|MaxAttemptsExceededException/i',
            'A background job kept failing and the app stopped retrying it.'],
        // Code-level bugs.
        ['/Class ["\'][^"\']*["\'] not found|Class [\w\\\\]+ does not exist|Target class \[[^\]]*\] does not exist/i',
            'The app referred to code that is missing. This needs a developer.'],
        ['/Vite manifest|Unable to locate file in Vite/i',
            'The app’s front-end assets have not been built. This needs a developer.'],
        ['/Call to undefined method|Call to a member function [\w]+\(\) on (null|bool|int|string|array)|Attempt to read property "[^"]*" on null|Undefined (variable|property|array key)|TypeError:|ErrorException/i',
            'The app hit a bug: it used a value that was empty or the wrong type. This needs a developer.'],
        ['/ViewException|Undefined variable \$/i',
            'A page failed to build because of a bug in its template. This needs a developer.'],
    ];

    public static function explain(string $message, ?string $trace = null): ?string
    {
        $haystack = $trace === null || $trace === '' ? $message : $message.' '.$trace;

        foreach (self::PATTERNS as [$pattern, $explanation]) {
            if (preg_match($pattern, $haystack) === 1) {
                return $explanation;
            }
        }

        return null;
    }

    /**
     * A safe fallback when `explain()` finds no specific match, keyed only off
     * the PSR-3 severity level.
     */
    public static function generic(string $level): string
    {
        return match (strtoupper($level)) {
            'EMERGENCY', 'ALERT', 'CRITICAL' => 'The app hit a serious problem it could not recover from on its own.',
            'ERROR' => 'The app ran into an unexpected problem while handling a request.',
            'WARNING' => 'Something unexpected happened, but the app kept working.',
            default => 'An informational message from the app — no action needed.',
        };
    }
}
