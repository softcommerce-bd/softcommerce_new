<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Order attribution for sales funnels: which funnel, and which published
 * version of it, an order was placed through.
 *
 * Both columns are nullable and every existing order keeps NULL, so no
 * existing row changes meaning. Foreign keys are added only when orders,
 * funnels and funnel_versions are all InnoDB; on any other engine the columns
 * and indexes are still created. ON DELETE SET NULL keeps an order intact if a
 * funnel row is ever hard-deleted (the app itself only soft-deletes funnels).
 */
class AddFunnelColumnsToOrdersTable extends Migration
{
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('funnel_id')->nullable()->after('order_from')->index();
            $table->unsignedBigInteger('funnel_version_id')->nullable()->after('funnel_id')->index();
        });

        if ($this->allInnoDb(['orders', 'funnels', 'funnel_versions'])) {
            Schema::table('orders', function (Blueprint $table) {
                $table->foreign('funnel_id')->references('id')->on('funnels')->nullOnDelete();
                $table->foreign('funnel_version_id')->references('id')->on('funnel_versions')->nullOnDelete();
            });
        }
    }

    public function down()
    {
        foreach (['orders_funnel_id_foreign', 'orders_funnel_version_id_foreign'] as $constraint) {
            if ($this->foreignKeyExists('orders', $constraint)) {
                Schema::table('orders', function (Blueprint $table) use ($constraint) {
                    $table->dropForeign($constraint);
                });
            }
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_funnel_version_id_index');
            $table->dropIndex('orders_funnel_id_index');
            $table->dropColumn(['funnel_version_id', 'funnel_id']);
        });
    }

    private function allInnoDb(array $tables): bool
    {
        if (DB::getDriverName() !== 'mysql') {
            return false;
        }

        $engines = DB::table('information_schema.TABLES')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->whereIn('TABLE_NAME', $tables)
            ->pluck('ENGINE', 'TABLE_NAME');

        foreach ($tables as $table) {
            if (strcasecmp((string) ($engines[$table] ?? ''), 'InnoDB') !== 0) {
                return false;
            }
        }

        return true;
    }

    private function foreignKeyExists(string $table, string $name): bool
    {
        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $name)
            ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
            ->exists();
    }
}
