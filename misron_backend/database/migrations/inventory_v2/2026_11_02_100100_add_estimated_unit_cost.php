<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Provisional (estimated) unit cost of an uncosted dispatched line: a planning figure only, never an accounting value. */
return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('order_details') && !Schema::hasColumn('order_details', 'estimated_unit_cost')) {
            Schema::table('order_details', fn (Blueprint $t) => $t->decimal('estimated_unit_cost', 20, 4)->nullable());
        }
    }

    public function down()
    {
        if (Schema::hasColumn('order_details', 'estimated_unit_cost')) {
            Schema::table('order_details', fn (Blueprint $t) => $t->dropColumn('estimated_unit_cost'));
        }
    }
};
