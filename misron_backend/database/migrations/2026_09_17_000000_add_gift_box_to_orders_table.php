<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gift box is an order-level add-on: one flat charge for the whole basket,
 * however many products or sellers it spans. The flag lives on `orders` so the
 * packing team sees it on the order they pack, and the charge is recorded
 * beside it so an order's grand total can still be reconciled from its parts.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'gift_box')) {
                $table->boolean('gift_box')->default(0)->after('grand_total');
            }
            if (!Schema::hasColumn('orders', 'gift_box_charge')) {
                $table->double('gift_box_charge', 20, 2)->default(0)->after('gift_box');
            }
        });
    }

    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['gift_box', 'gift_box_charge']);
        });
    }
};
