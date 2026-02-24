<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_addresses', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('customer_id');
            $table->enum('type', ['billing', 'shipping']);
            $table->string('line1');
            $table->string('line2')->nullable();
            $table->string('city');
            $table->string('state')->nullable();
            $table->string('postal_code');
            $table->string('country_code', 2);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();
            $table->index('customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_addresses');
    }
};
