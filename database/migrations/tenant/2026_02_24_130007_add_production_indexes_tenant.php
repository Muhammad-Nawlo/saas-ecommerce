<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->index(['tenant_id', 'is_active']);
            $table->index(['tenant_id', 'slug']);
        });
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table): void {
                if (!$this->indexExists('orders', 'orders_tenant_id_created_at_index')) {
                    $table->index(['tenant_id', 'created_at']);
                }
            });
        }
        if (Schema::hasTable('carts')) {
            Schema::table('carts', function (Blueprint $table): void {
                $table->index(['tenant_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id', 'is_active']);
            $table->dropIndex(['tenant_id', 'slug']);
        });
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->dropIndex(['tenant_id', 'created_at']);
            });
        }
        if (Schema::hasTable('carts')) {
            Schema::table('carts', function (Blueprint $table): void {
                $table->dropIndex(['tenant_id', 'status']);
            });
        }
    }

    private function indexExists(string $table, string $name): bool
    {
        $indexes = Schema::getIndexListing($table);
        return in_array($name, $indexes, true);
    }
};
