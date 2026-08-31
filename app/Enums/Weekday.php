<?php

namespace App\Enums;

use Illuminate\Support\Carbon;

/**
 * A day of the week, stored lowercase to match Carbon's englishDayOfWeek.
 * The organization has one default weekly off day (§85) and any employee
 * may override it with their own (docs/PRD.md §5683 — the client-aligned
 * team case). Resolution is always "(employee, date)".
 */
enum Weekday: string
{
    case Monday = 'monday';
    case Tuesday = 'tuesday';
    case Wednesday = 'wednesday';
    case Thursday = 'thursday';
    case Friday = 'friday';
    case Saturday = 'saturday';
    case Sunday = 'sunday';

    public function matches(Carbon $date): bool
    {
        return strtolower($date->englishDayOfWeek) === $this->value;
    }

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $day) => $day->value, self::cases());
    }
}
