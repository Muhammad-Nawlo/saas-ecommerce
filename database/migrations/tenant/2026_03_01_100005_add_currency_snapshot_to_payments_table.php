<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->string('payment_currency', 3)->nullable()->after('currency');
            $table->unsignedBigInteger('payment_amount')->nullable()->after('payment_currency')->comment('Amount in payment currency (minor units)');
            $table->json('exchange_rate_snapshot')->nullable()->after('payment_amount');
            $table->bigInteger('payment_amount_base')->nullable()->after('exchange_rate_snapshot')->comment('Amount in base currency (minor units)');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropColumn(['payment_currency', 'payment_amount', 'exchange_rate_snapshot', 'payment_amount_base']);
        });
    }
};
