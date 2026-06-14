<?php

declare(strict_types=1);

namespace Trail\Trail\Mcp\Dashboard;

use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Trail\Trail\Mcp\Dashboard\Prompts\AnalysisPrompt;
use Trail\Trail\Mcp\Dashboard\Tools\CatalogTool;
use Trail\Trail\Mcp\Dashboard\Tools\EventsTool;
use Trail\Trail\Mcp\Dashboard\Tools\FunnelTool;
use Trail\Trail\Mcp\Dashboard\Tools\MetricsTool;

#[Name('Trail Dashboard')]
#[Version('1.0.0')]
#[Instructions('Read-only analytics over this application Trail data. Start with the trail_analysis prompt, then trail_catalog. All tools are read-only.')]
class DashboardMcpServer extends Server
{
    /**
     * @var array<int, class-string<\Laravel\Mcp\Server\Tool>>
     */
    protected array $tools = [
        CatalogTool::class,
        MetricsTool::class,
        FunnelTool::class,
        EventsTool::class,
    ];

    /**
     * @var array<int, class-string<\Laravel\Mcp\Server\Prompt>>
     */
    protected array $prompts = [
        AnalysisPrompt::class,
    ];
}
