<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_audit_logs', function (Blueprint $table): void {
            if (! Schema::hasColumn('tenant_audit_logs', 'tenant_id')) {
                $table->string('tenant_id', 36)->nullable()->after('id')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenant_audit_logs', function (Blueprint $table): void {
            $table->dropColumn('tenant_id');
        });
    }
};
