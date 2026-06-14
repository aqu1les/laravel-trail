<?php

declare(strict_types=1);

namespace Trail\Trail\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IngestController
{
    public function store(Request $request): JsonResponse
    {
        return response()->json(['accepted' => 0], 202);
    }
}
