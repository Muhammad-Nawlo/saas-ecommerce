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
        Schema::connection($conn)->create('stripe_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('event_id', 255)->unique();
            $table->timestamp('processed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $conn = config('tenancy.database.central_connection', config('database.default'));
        Schema::connection($conn)->dropIfExists('stripe_events');
    }
};
