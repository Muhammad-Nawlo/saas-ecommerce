<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $conn = config('tenancy.database.central_connection', config('database.default'));
        Schema::connection($conn)->table('subscriptions', function (Blueprint $table) use ($conn): void {
            if (!Schema::connection($conn)->hasColumn('subscriptions', 'starts_at')) {
                $table->dateTime('starts_at')->nullable()->after('plan_id');
            }
            if (!Schema::connection($conn)->hasColumn('subscriptions', 'ends_at')) {
                $table->dateTime('ends_at')->nullable()->after('starts_at');
            }
        });
    }

    public function down(): void
    {
        $conn = config('tenancy.database.central_connection', config('database.default'));
        Schema::connection($conn)->table('subscriptions', function (Blueprint $table) use ($conn): void {
            $table->dropColumn(['starts_at', 'ends_at']);
        });
    }
};
