<?php

declare(strict_types=1);

namespace Trail\Trail\Contracts;

use Illuminate\Http\Request;

interface ContextCaptureContract
{
    /** @return array<string, mixed> */
    public function fromRequest(Request $request): array;

    /** @return array<string, mixed> */
    public function fromConsole(): array;
}
