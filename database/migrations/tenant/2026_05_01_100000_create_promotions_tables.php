<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Promotion engine: promotions, coupon_codes, promotion_usages.
 * Supports percentage, fixed, free_shipping, threshold, bogo. Immutable snapshot in order after lock.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('tenant_id', 36)->index();
            $table->string('name');
            $table->string('type', 32); // percentage, fixed, free_shipping, threshold, bogo
            $table->unsignedInteger('value_cents')->default(0); // fixed amount or threshold; percentage stored as 0-10000 (e.g. 1000 = 10%)
            $table->unsignedDecimal('percentage', 5, 2)->nullable(); // for percentage type
            $table->unsignedBigInteger('min_cart_cents')->default(0);
            $table->unsignedBigInteger('buy_quantity')->nullable(); // for bogo: buy X
            $table->unsignedBigInteger('get_quantity')->nullable(); // for bogo: get Y free
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_stackable')->default(false);
            $table->unsignedInteger('max_uses_total')->nullable();
            $table->unsignedInteger('max_uses_per_customer')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['tenant_id', 'is_active', 'starts_at', 'ends_at']);
        });

        Schema::create('coupon_codes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('promotion_id');
            $table->string('code', 64)->index();
            $table->unsignedInteger('usage_count')->default(0);
            $table->timestamps();
            $table->unique(['promotion_id', 'code']);
            $table->foreign('promotion_id')->references('id')->on('promotions')->cascadeOnDelete();
        });

        Schema::create('promotion_usages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('promotion_id');
            $table->string('customer_id', 36)->nullable()->index();
            $table->string('customer_email')->nullable()->index();
            $table->string('order_id', 36)->nullable()->index();
            $table->timestamp('used_at')->useCurrent();
            $table->foreign('promotion_id')->references('id')->on('promotions')->cascadeOnDelete();
            $table->index(['promotion_id', 'customer_id']);
            $table->index(['promotion_id', 'customer_email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_usages');
        Schema::dropIfExists('coupon_codes');
        Schema::dropIfExists('promotions');
    }
};
