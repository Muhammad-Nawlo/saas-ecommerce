<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Used by InvoiceNumberGenerator for INV-YYYY-XXXX. Row lock prevents duplicates.
     */
    public function up(): void
    {
        Schema::create('invoice_number_sequence', function (Blueprint $table): void {
            $table->string('tenant_id', 36);
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();

            $table->primary(['tenant_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_number_sequence');
    }
};
