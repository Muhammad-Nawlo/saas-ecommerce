<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\ChangePasswordRequest;
use App\Http\Requests\Api\V1\Customer\DeleteAccountRequest;
use App\Models\Customer\Customer;
use App\Services\Customer\CustomerDataExportService;
use App\Services\Customer\CustomerDeletionService;
use App\Modules\Shared\Infrastructure\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AccountController extends Controller
{
    public function __construct(
        private AuditLogger $auditLogger,
        private CustomerDataExportService $exportService,
        private CustomerDeletionService $deletionService,
    ) {}

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $customer = Auth::guard('customer')->user();
        $customer->update(['password' => Hash::make($request->validated('password'))]);
        $customer->tokens()->delete();

        $this->auditLogger->logTenantAction(
            'customer_password_changed',
            'Customer password changed: ' . $customer->email,
            $customer,
            [
                'actor_id' => $customer->id,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'timestamp' => now()->toIso8601String(),
            ],
        );

        return response()->json(['message' => 'Password updated. Please log in again.']);
    }

    /** GDPR: export all customer data. */
    public function export(Request $request): JsonResponse
    {
        $customer = Auth::guard('customer')->user();
        $data = $this->exportService->export($customer);
        return response()->json(['data' => $data]);
    }

    /** GDPR: self-service account deletion (soft delete then anonymize). */
    public function destroy(DeleteAccountRequest $request): JsonResponse
    {
        $customer = Auth::guard('customer')->user();
        $this->deletionService->deleteAndAnonymize($customer);
        $customer->tokens()->delete();

        Auth::guard('customer')->logout();

        return response()->json(['message' => 'Account deleted.'], 200);
    }
}
