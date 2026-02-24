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
        Schema::connection($conn)->table('features', function (Blueprint $table): void {
            if (!Schema::connection($conn)->hasColumn('features', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        $conn = config('tenancy.database.central_connection', config('database.default'));
        Schema::connection($conn)->table('features', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });
    }
};
