<?php

namespace App\Support\Logs;

/**
 * PSR-3 / Monolog level names ordered by severity, for the log viewer's
 * minimum-level filter and its "error or higher" checks.
 */
class LogLevel
{
    /** @var array<string, int> */
    public const WEIGHTS = [
        'DEBUG' => 100,
        'INFO' => 200,
        'NOTICE' => 250,
        'WARNING' => 300,
        'ERROR' => 400,
        'CRITICAL' => 500,
        'ALERT' => 550,
        'EMERGENCY' => 600,
    ];

    public const ERROR_THRESHOLD = 400;

    public static function weight(string $level): int
    {
        return self::WEIGHTS[strtoupper($level)] ?? self::WEIGHTS['ERROR'];
    }

    public static function isErrorOrHigher(string $level): bool
    {
        return self::weight($level) >= self::ERROR_THRESHOLD;
    }

    /** @return list<string> */
    public static function names(): array
    {
        return array_keys(self::WEIGHTS);
    }
}
