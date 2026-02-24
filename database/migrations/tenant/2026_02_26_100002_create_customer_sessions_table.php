<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('customer_id');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();
            $table->index(['customer_id', 'last_activity_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_sessions');
    }
};
