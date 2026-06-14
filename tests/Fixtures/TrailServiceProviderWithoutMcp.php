<?php

declare(strict_types=1);

namespace Trail\Trail\Tests\Fixtures;

use Trail\Trail\TrailServiceProvider;

class TrailServiceProviderWithoutMcp extends TrailServiceProvider
{
    /**
     * Force the "laravel/mcp not installed" branch for testing.
     */
    protected function mcpServerAvailable(): bool
    {
        return false;
    }
}
