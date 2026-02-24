<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->string('base_currency', 3)->nullable()->after('currency');
            $table->json('exchange_rate_snapshot')->nullable()->after('base_currency');
            $table->bigInteger('total_base_cents')->nullable()->after('total_cents');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn(['base_currency', 'exchange_rate_snapshot', 'total_base_cents']);
        });
    }
};
