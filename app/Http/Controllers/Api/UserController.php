<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class UserController
{
    public function show(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }
}
