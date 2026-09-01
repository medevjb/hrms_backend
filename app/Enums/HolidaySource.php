<?php

namespace App\Enums;

/**
 * Where a Holiday row came from. MANUAL is anything Head HR added by hand;
 * GOOGLE_BD is a public holiday pulled from Google's "Holidays in
 * Bangladesh" calendar by the holidays:import-bd importer, which only
 * ever touches its own rows.
 */
enum HolidaySource: string
{
    case Manual = 'MANUAL';
    case GoogleBd = 'GOOGLE_BD';
}
