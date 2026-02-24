<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->uuid('customer_id')->nullable()->after('tenant_id')->index();
            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropForeign(['customer_id']);
            $table->dropColumn('customer_id');
        });
    }
};
