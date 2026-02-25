<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection($this->connection())->create('plans', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('stripe_price_id');
            $table->unsignedInteger('price_amount');
            $table->string('currency', 3);
            $table->string('billing_interval', 20);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection())->dropIfExists('plans');
    }

    private function connection(): string
    {
        return config('tenancy.database.central_connection', config('database.default'));
    }
};
