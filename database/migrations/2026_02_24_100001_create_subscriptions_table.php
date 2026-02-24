<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection($this->connection())->create('subscriptions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('plan_id')->index();
            $table->string('stripe_subscription_id')->unique();
            $table->string('status', 20);
            $table->dateTime('current_period_start');
            $table->dateTime('current_period_end');
            $table->boolean('cancel_at_period_end')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection())->dropIfExists('subscriptions');
    }

    private function connection(): string
    {
        return config('tenancy.database.central_connection', config('database.default'));
    }
};
