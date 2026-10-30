<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Columns on existing tables. All nullable or defaulted, so existing rows and existing code keep working:
 *  - products.stock_policy defaults to 'stock_only' (today's behaviour)
 *  - order_details.stock_mode defaults to 'legacy' (stock already deducted when the order was created)
 * And account_statement_logs.transaction_type is widened from a closed ENUM to a string: the ENUM silently turned
 * every value the code wrote that it did not list (purchase, purchase_payment, order_cancelled ...) into ''.
 * Existing '' values are left as they are: their original type cannot be reconstructed with certainty.
 */
class AddInventoryV2Columns extends Migration
{
    public function up()
    {
        Schema::table('products', function (Blueprint $t) {
            $t->string('stock_policy', 12)->default('stock_only');          // stock_only | backorder | preorder
            $t->unsignedInteger('max_backorder_qty')->nullable();
            $t->unsignedInteger('max_preorder_qty')->nullable();
            $t->date('preorder_available_at')->nullable();
            $t->unsignedBigInteger('preferred_supplier_id')->nullable();
            $t->decimal('estimated_purchase_cost', 20, 4)->nullable();      // an estimate for planning only, never used as actual cost
            $t->unsignedSmallInteger('supplier_lead_time_days')->nullable();
        });

        Schema::table('order_details', function (Blueprint $t) {
            $t->string('stock_mode', 8)->default('legacy');                 // legacy | v2
            $t->string('cost_status', 12)->nullable();                      // final | uncosted (v2 lines, set at dispatch)
            $t->decimal('provisional_cost', 20, 4)->nullable();             // planning estimate while a line is awaiting stock; never COGS
            $t->index(['stock_mode', 'order_id'], 'idx_order_details_mode');
            $t->index('order_id', 'idx_order_details_order');
        });

        // Legacy purchase orders could not hold a variation and only knew 'draft' / 'purchased'.
        Schema::table('purchase_order_details', function (Blueprint $t) {
            $t->string('variant', 255)->nullable();
            $t->index(['product_id', 'variant'], 'idx_po_details_product_variant');
        });
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `purchase_orders` MODIFY `status` VARCHAR(20) NOT NULL DEFAULT 'draft'"); // draft | ordered | partially_received | received | cancelled | purchased(legacy)
        }

        // Clearing accounts the purchasing flow posts to. Created here, only where missing, so nothing is created while a request is running.
        // Inventory and Accounts Payable already exist in a live system; they are looked up by name, never altered.
        foreach (\App\Services\Accounting\SystemAccounts::definitions() as [$name, $type, $sub, $code]) {
            $exists = DB::table('chart_of_accounts')->where(fn ($q) => $q->where('name', $name)->where('type', $type))->orWhere('code', $code)->exists();
            if (!$exists) {
                DB::table('chart_of_accounts')->insert(['code' => $code, 'name' => $name, 'type' => $type, 'sub_type' => $sub, 'opening_balance' => 0, 'current_balance' => 0, 'status' => 'active', 'is_deleted' => 0,
                    'description' => 'Created by the inventory/purchasing upgrade.', 'created_at' => now(), 'updated_at' => now()]);
            }
        }

        // ENUM -> VARCHAR keeps every stored value, including the blanks.
        if (Schema::hasTable('account_statement_logs') && DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `account_statement_logs` MODIFY `transaction_type` VARCHAR(50) NULL');
        }
    }

    public function down()
    {
        Schema::table('purchase_order_details', function (Blueprint $t) {
            $t->dropIndex('idx_po_details_product_variant');
            $t->dropColumn('variant');
        });
        Schema::table('order_details', function (Blueprint $t) {
            $t->dropIndex('idx_order_details_mode');
            $t->dropIndex('idx_order_details_order');
            $t->dropColumn(['stock_mode', 'cost_status', 'provisional_cost']);
        });
        Schema::table('products', function (Blueprint $t) {
            $t->dropColumn(['stock_policy', 'max_backorder_qty', 'max_preorder_qty', 'preorder_available_at', 'preferred_supplier_id', 'estimated_purchase_cost', 'supplier_lead_time_days']);
        });
        // The ENUM is deliberately not restored: narrowing it would blank values written since.
    }
}
