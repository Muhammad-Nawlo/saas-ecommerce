<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant audit logs. Stored in tenant DB only. Run via tenants:migrate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('event_type')->index();
            $table->string('model_type')->index();
            $table->string('model_id', 36)->nullable()->index();
            $table->text('description');
            $table->json('properties')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['model_type', 'model_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_audit_logs');
    }
};
