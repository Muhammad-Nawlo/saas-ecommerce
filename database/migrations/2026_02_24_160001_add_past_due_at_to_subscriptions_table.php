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
            if (!Schema::connection($conn)->hasColumn('subscriptions', 'past_due_at')) {
                $table->dateTime('past_due_at')->nullable()->after('cancel_at_period_end');
            }
        });
    }

    public function down(): void
    {
        $conn = config('tenancy.database.central_connection', config('database.default'));
        Schema::connection($conn)->table('subscriptions', function (Blueprint $table) use ($conn): void {
            $table->dropColumn('past_due_at');
        });
    }
};
