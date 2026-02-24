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
        Schema::connection($conn)->create('features', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code', 100)->unique();
            $table->string('description')->nullable();
            $table->string('type', 20);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $conn = config('tenancy.database.central_connection', config('database.default'));
        Schema::connection($conn)->dropIfExists('features');
    }
};
