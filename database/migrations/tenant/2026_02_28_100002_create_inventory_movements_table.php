<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Append-only log of all stock changes. */
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('product_id')->index();
            $table->uuid('location_id')->index();
            $table->enum('type', [
                'increase',
                'decrease',
                'reserve',
                'release',
                'transfer_out',
                'transfer_in',
                'adjustment',
            ]);
            $table->integer('quantity');
            $table->string('reference_type', 64)->nullable();
            $table->string('reference_id', 36)->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['location_id', 'created_at']);
            $table->index(['product_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
