<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * docs/PRD.md §136 — a punch outside every candidate shift's matching
 * window is rejected outright rather than silently attached to the wrong
 * day; HR handles it as a manual correction (§32) instead.
 */
class AttendanceWindowException extends RuntimeException
{
    public function __construct(string $message)
    {
        parent::__construct($message);
    }
}
