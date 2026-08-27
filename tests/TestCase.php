<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // RefreshDatabase rolls back each test's DB transaction, but the
        // array cache store is not transaction-scoped and persists across
        // tests in the same process — a cached row (e.g.
        // OrganizationSettings::current()) can end up pointing at an id
        // from a previous, now-rolled-back test. Found this the hard way.
        Cache::flush();
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
