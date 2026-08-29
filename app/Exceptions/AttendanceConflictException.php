<?php

namespace App\Exceptions;

use App\Models\AttendanceRecord;
use RuntimeException;

/**
 * docs/PRD.md §139.5 — a duplicate check-in/check-out (or a check-out with
 * no open check-in) returns 409 with a stable `code` and the existing
 * record in `data`, so the frontend can render the correct state from the
 * error alone rather than needing a follow-up GET.
 */
class AttendanceConflictException extends RuntimeException
{
    /**
     * Named errorCode, not code — Exception already declares a non-readonly
     * $code property, and PHP won't let a subclass redeclare it readonly.
     */
    public readonly string $errorCode;

    public readonly ?AttendanceRecord $record;

    public function __construct(string $errorCode, ?AttendanceRecord $record, string $message)
    {
        parent::__construct($message);

        $this->errorCode = $errorCode;
        $this->record = $record;
    }
}
