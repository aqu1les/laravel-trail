<?php

declare(strict_types=1);

use Trail\Trail\Mcp\Dashboard\DashboardMcpServer;
use Trail\Trail\Mcp\Dashboard\Prompts\AnalysisPrompt;

it('returns analysis routing guidance', function () {
    DashboardMcpServer::prompt(AnalysisPrompt::class, [])
        ->assertOk()
        ->assertSee('trail_catalog')   // tells the LLM to start here
        ->assertSee('truncated');      // tells it to treat truncated as a sample
});
