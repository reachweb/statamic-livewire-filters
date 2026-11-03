<?php

namespace Reach\StatamicLivewireFilters\Tests\Feature;

use Closure;
use Illuminate\Support\Facades\Config;

/** Every HandleFieldBoundsTest scenario, with the bounds routed through the facet engine. */
class HandleFieldBoundsWithFacetEngineTest extends HandleFieldBoundsTest
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('statamic-livewire-filters.enable_facet_engine', true);
    }

    /**
     * The parent's pool mocks set expectations on getFieldValues() only; the engine's
     * registration and prefetch calls on the same pool must run for real.
     */
    protected function mock($abstract, ?Closure $mock = null)
    {
        return $this->partialMock($abstract, $mock);
    }
}
