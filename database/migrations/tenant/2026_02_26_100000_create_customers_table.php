<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('tenant_id', 36)->index();
            $table->string('email')->index();
            $table->string('password');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('phone')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('last_login_at')->nullable()->index();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
