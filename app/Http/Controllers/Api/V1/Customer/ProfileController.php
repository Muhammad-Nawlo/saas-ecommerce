<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\UpdateProfileRequest;
use App\Http\Resources\Api\V1\Customer\CustomerResource;
use App\Modules\Shared\Infrastructure\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller
{
    public function __construct(
        private AuditLogger $auditLogger,
    ) {}

    public function me(): JsonResponse
    {
        $customer = Auth::guard('customer')->user();
        return response()->json(['data' => new CustomerResource($customer)]);
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $customer = Auth::guard('customer')->user();
        $customer->update($request->validated());

        $this->auditLogger->logTenantAction(
            'customer_profile_updated',
            'Customer profile updated: ' . $customer->email,
            $customer,
            [
                'actor_id' => $customer->id,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'timestamp' => now()->toIso8601String(),
            ],
        );

        return response()->json(['data' => new CustomerResource($customer->fresh())]);
    }
}
