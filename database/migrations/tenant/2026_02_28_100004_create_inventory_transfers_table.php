<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_transfers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('product_id')->index();
            $table->uuid('from_location_id')->index();
            $table->uuid('to_location_id')->index();
            $table->unsignedInteger('quantity');
            $table->enum('status', ['pending', 'completed', 'cancelled'])->default('pending')->index();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();

            $table->foreign('from_location_id')->references('id')->on('inventory_locations')->cascadeOnDelete();
            $table->foreign('to_location_id')->references('id')->on('inventory_locations')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_transfers');
    }
};
