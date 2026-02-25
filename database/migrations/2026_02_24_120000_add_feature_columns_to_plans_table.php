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
        Schema::connection($conn)->table('plans', function (Blueprint $table) use ($conn): void {
            if (! Schema::connection($conn)->hasColumn('plans', 'code')) {
                $table->string('code', 50)->nullable()->unique()->after('name');
            }
            if (!Schema::connection($conn)->hasColumn('plans', 'price')) {
                $table->decimal('price', 10, 2)->default(0)->after('code');
            }
            if (!Schema::connection($conn)->hasColumn('plans', 'billing_interval')) {
                $table->string('billing_interval', 20)->default('monthly')->after('price');
            }
        });
    }

    public function down(): void
    {
        $conn = config('tenancy.database.central_connection', config('database.default'));
        Schema::connection($conn)->table('plans', function (Blueprint $table) use ($conn): void {
            $table->dropColumn(['code', 'price']);
        });
    }
};
