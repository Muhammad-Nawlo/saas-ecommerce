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
        Schema::connection($conn)->create('plan_features', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('plan_id');
            $table->uuid('feature_id');
            $table->string('value');
            $table->timestamps();
            $table->unique(['plan_id', 'feature_id']);
            $table->foreign('plan_id')->references('id')->on('plans')->cascadeOnDelete();
            $table->foreign('feature_id')->references('id')->on('features')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        $conn = config('tenancy.database.central_connection', config('database.default'));
        Schema::connection($conn)->dropIfExists('plan_features');
    }
};
