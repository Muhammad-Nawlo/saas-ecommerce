<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\StoreAddressRequest;
use App\Http\Requests\Api\V1\Customer\UpdateAddressRequest;
use App\Http\Resources\Api\V1\Customer\CustomerAddressResource;
use App\Models\Customer\CustomerAddress;
use App\Modules\Shared\Infrastructure\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class AddressController extends Controller
{
    public function __construct(
        private AuditLogger $auditLogger,
    ) {}

    public function index(): JsonResponse
    {
        $customer = Auth::guard('customer')->user();
        $addresses = $customer->addresses()->orderBy('type')->orderBy('is_default', 'desc')->get();
        return response()->json(['data' => CustomerAddressResource::collection($addresses)]);
    }

    public function store(StoreAddressRequest $request): JsonResponse
    {
        $customer = Auth::guard('customer')->user();
        $address = $customer->addresses()->create($request->validated());

        $this->auditLogger->logTenantAction(
            'address_created',
            'Address created for customer: ' . $customer->email,
            $address,
            [
                'actor_id' => $customer->id,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'timestamp' => now()->toIso8601String(),
            ],
        );

        return response()->json(['data' => new CustomerAddressResource($address)], 201);
    }

    public function update(UpdateAddressRequest $request, string $id): JsonResponse
    {
        $customer = Auth::guard('customer')->user();
        $address = $customer->addresses()->findOrFail($id);
        $address->update($request->validated());
        return response()->json(['data' => new CustomerAddressResource($address->fresh())]);
    }

    public function destroy(string $id): JsonResponse
    {
        $customer = Auth::guard('customer')->user();
        $address = $customer->addresses()->findOrFail($id);
        $address->delete();

        $this->auditLogger->logTenantAction(
            'address_deleted',
            'Address deleted for customer: ' . $customer->email,
            null,
            [
                'actor_id' => $customer->id,
                'address_id' => $id,
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'timestamp' => now()->toIso8601String(),
            ],
        );

        return response()->json(['message' => 'Address deleted.'], 204);
    }
}
