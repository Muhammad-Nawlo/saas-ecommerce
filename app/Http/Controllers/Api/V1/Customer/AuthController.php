<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\CustomerLoginRequest;
use App\Http\Requests\Api\V1\Customer\CustomerRegisterRequest;
use App\Models\Customer\Customer;
use App\Modules\Shared\Infrastructure\Audit\AuditLogger;
use App\Services\Customer\LinkGuestOrdersToCustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private AuditLogger $auditLogger,
        private LinkGuestOrdersToCustomerService $linkGuestOrders,
    ) {}

    /**
     * Register a new customer. Tenant-scoped; email unique per tenant.
     */
    public function register(CustomerRegisterRequest $request): JsonResponse
    {
        $tenantId = (string) tenant('id');
        if ($tenantId === '') {
            return response()->json(['message' => 'Tenant context required.'], 400);
        }

        $customer = Customer::create([
            'tenant_id' => $tenantId,
            'email' => strtolower($request->validated('email')),
            'password' => Hash::make($request->validated('password')),
            'first_name' => $request->validated('first_name'),
            'last_name' => $request->validated('last_name'),
            'phone' => $request->validated('phone'),
            'is_active' => true,
        ]);

        $this->auditLogger->logTenantAction(
            'customer_registered',
            'Customer registered: ' . $customer->email,
            $customer,
            [
                'actor_id' => $customer->id,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'timestamp' => now()->toIso8601String(),
            ],
        );

        $this->linkGuestOrders->linkByEmail($customer);

        $token = $customer->createToken('storefront')->plainTextToken;

        return response()->json([
            'message' => 'Registered successfully.',
            'token' => $token,
            'token_type' => 'Bearer',
            'customer' => new \App\Http\Resources\Api\V1\Customer\CustomerResource($customer),
        ], 201);
    }

    /**
     * Login. Generic error on failure to prevent enumeration.
     */
    public function login(CustomerLoginRequest $request): JsonResponse
    {
        $tenantId = (string) tenant('id');
        if ($tenantId === '') {
            return response()->json(['message' => 'Tenant context required.'], 400);
        }

        $customer = Customer::forTenant($tenantId)
            ->where('email', strtolower($request->validated('email')))
            ->first();

        if (!$customer || !Hash::check($request->validated('password'), $customer->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        if (!$customer->canLogin()) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        $customer->update(['last_login_at' => now()]);

        $this->auditLogger->logTenantAction(
            'customer_logged_in',
            'Customer logged in: ' . $customer->email,
            $customer,
            [
                'actor_id' => $customer->id,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'timestamp' => now()->toIso8601String(),
            ],
        );

        $token = $customer->createToken('storefront')->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'customer' => new \App\Http\Resources\Api\V1\Customer\CustomerResource($customer),
        ]);
    }

    /**
     * Logout: revoke current token.
     */
    public function logout(): JsonResponse
    {
        $customer = Auth::guard('customer')->user();
        if ($customer instanceof Customer) {
            $customer->currentAccessToken()?->delete();
        }

        return response()->json(['message' => 'Logged out.']);
    }
}
