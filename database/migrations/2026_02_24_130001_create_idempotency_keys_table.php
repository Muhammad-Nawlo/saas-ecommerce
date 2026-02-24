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
        Schema::connection($conn)->create('idempotency_keys', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('tenant_id', 36)->index();
            $table->string('key', 128)->index();
            $table->string('endpoint', 255);
            $table->string('response_hash', 64)->nullable();
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'key']);
        });
    }

    public function down(): void
    {
        $conn = config('tenancy.database.central_connection', config('database.default'));
        Schema::connection($conn)->dropIfExists('idempotency_keys');
    }
};
