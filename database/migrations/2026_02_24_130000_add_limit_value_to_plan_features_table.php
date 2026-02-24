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
        Schema::connection($conn)->table('plan_features', function (Blueprint $table): void {
            if (!Schema::connection($conn)->hasColumn('plan_features', 'limit_value')) {
                $table->integer('limit_value')->nullable()->after('value');
            }
        });
    }

    public function down(): void
    {
        $conn = config('tenancy.database.central_connection', config('database.default'));
        Schema::connection($conn)->table('plan_features', function (Blueprint $table): void {
            $table->dropColumn('limit_value');
        });
    }
};
