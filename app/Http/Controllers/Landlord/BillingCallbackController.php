<?php

declare(strict_types=1);

namespace App\Http\Controllers\Landlord;

use Illuminate\Http\JsonResponse;

final class BillingCallbackController
{
    public function success(): JsonResponse
    {
        return response()->json(['message' => 'Checkout successful']);
    }

    public function cancel(): JsonResponse
    {
        return response()->json(['message' => 'Checkout cancelled']);
    }

    public function portalReturn(): JsonResponse
    {
        return response()->json(['message' => 'Returned from billing portal']);
    }
}
