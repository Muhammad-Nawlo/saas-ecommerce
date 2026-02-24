<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\ForgotPasswordRequest;
use App\Http\Requests\Api\V1\Customer\ResetPasswordRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;

class PasswordController extends Controller
{
    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        $status = Password::broker('customers')->sendResetLink(
            $request->only('email'),
        );

        if ($status !== Password::RESET_LINK_SENT) {
            return response()->json(['message' => __('passwords.user')], 400);
        }

        return response()->json(['message' => __('passwords.sent')]);
    }

    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::broker('customers')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($customer, string $password): void {
                $customer->forceFill(['password' => $password])->save();
                $customer->tokens()->delete();
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json(['message' => __('passwords.token')], 400);
        }

        return response()->json(['message' => __('passwords.reset')]);
    }
}
