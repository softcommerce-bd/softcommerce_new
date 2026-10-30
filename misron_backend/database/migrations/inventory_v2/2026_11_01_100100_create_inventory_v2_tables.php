<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inventory v2 / accounting foundation. Additive only: new tables, no existing table is altered here.
 *
 * Ownership of each quantity:
 *   physical on-hand ........ product_stocks.qty            (existing column, kept as the single on-hand figure)
 *   confirmed demand ........ order_demands                 (one row per order line; outstanding = confirmed - dispatched - cancelled)
 *   allocated ............... order_demands.qty_allocated   (subset of outstanding demand that has physical units earmarked)
 *   incoming ................ purchase_order_details (ordered - received) + purchase_requirement_links
 *   cost layers (FIFO) ...... purchase_products.remaining_qty (existing)
 * stock_balances holds per-(product,variant) counters (demand, allocated) maintained in the same transaction as every
 * change and re-derivable from order_demands (see `inventory:reconcile`). stock_movements is the audit ledger.
 */
class CreateInventoryV2Tables extends Migration
{
    public function up()
    {
        Schema::create('idempotency_keys', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('scope', 40);
            $t->string('key', 120);
            $t->longText('result')->nullable();
            $t->timestamps();
            $t->unique(['scope', 'key']);
        });

        Schema::create('stock_balances', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('product_id');
            $t->string('variant', 255)->default('');
            $t->integer('demand')->default(0);      // outstanding confirmed demand
            $t->integer('allocated')->default(0);   // physical units earmarked for that demand
            $t->integer('damaged')->default(0);     // received back as non-sellable, never added to on-hand
            $t->timestamps();
            $t->unique(['product_id', 'variant'], 'uq_stock_balances_product_variant');
        });

        Schema::create('order_demands', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('order_id');
            $t->unsignedBigInteger('order_detail_id');
            $t->unsignedBigInteger('product_id');
            $t->string('variant', 255)->default('');
            $t->integer('qty_confirmed');
            $t->integer('qty_allocated')->default(0);
            $t->integer('qty_dispatched')->default(0);
            $t->integer('qty_cancelled')->default(0);
            $t->string('status', 12)->default('open'); // open | fulfilled | cancelled
            $t->timestamp('confirmed_at')->nullable();
            $t->timestamps();
            $t->unique('order_detail_id', 'uq_order_demands_detail');
            $t->index(['product_id', 'variant', 'status', 'confirmed_at'], 'idx_order_demands_alloc');
            $t->index('order_id');
        });

        Schema::create('stock_movements', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('product_id');
            $t->string('variant', 255)->default('');
            $t->string('type', 30);               // demand_confirmed | demand_released | demand_adjusted | allocated | deallocated | dispatched | goods_received | return_received | return_damaged | supplier_return | adjustment
            $t->integer('on_hand_delta')->default(0);
            $t->integer('demand_delta')->default(0);
            $t->integer('allocated_delta')->default(0);
            $t->string('source_type', 40)->nullable();
            $t->unsignedBigInteger('source_id')->nullable();
            $t->unsignedBigInteger('order_detail_id')->nullable();
            $t->string('note', 255)->nullable();
            $t->unsignedInteger('created_by')->nullable();
            $t->timestamp('created_at')->nullable();
            $t->index(['product_id', 'variant', 'id'], 'idx_stock_movements_item');
            $t->index(['source_type', 'source_id']);
            $t->index('order_detail_id');
        });

        Schema::create('stock_allocations', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('order_demand_id');
            $t->unsignedBigInteger('stock_movement_id')->nullable();
            $t->string('action', 12);             // allocate | deallocate | dispatch
            $t->integer('qty');
            $t->string('fifo_batch_info', 1000)->nullable(); // for dispatch: the cost layers consumed
            $t->timestamp('created_at')->nullable();
            $t->index('order_demand_id');
        });

        Schema::create('goods_receipts', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('receipt_no', 40)->unique();
            $t->unsignedBigInteger('purchase_order_id')->nullable();
            $t->unsignedBigInteger('supplier_id');
            $t->unsignedBigInteger('purchase_id')->nullable();        // legacy purchases row that carries the FIFO cost layers
            $t->date('received_at');
            $t->decimal('goods_total', 20, 4)->default(0);
            $t->decimal('landed_cost', 20, 4)->default(0);            // freight / other capitalised cost allocated to the lines
            $t->decimal('total', 20, 4)->default(0);
            $t->string('invoice_status', 15)->default('not_invoiced'); // not_invoiced | invoiced
            $t->unsignedBigInteger('journal_entry_id')->nullable();
            $t->string('status', 12)->default('posted');              // posted | reversed
            $t->string('idempotency_key', 120)->unique();
            $t->unsignedInteger('created_by')->nullable();
            $t->string('note', 500)->nullable();
            $t->timestamps();
            $t->index('purchase_order_id');
            $t->index('supplier_id');
        });

        Schema::create('goods_receipt_lines', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('goods_receipt_id');
            $t->unsignedBigInteger('purchase_order_detail_id')->nullable();
            $t->unsignedBigInteger('product_id');
            $t->string('variant', 255)->default('');
            $t->integer('qty');
            $t->decimal('unit_cost', 20, 4);
            $t->decimal('landed_unit_cost', 20, 4);                   // unit_cost + allocated landed cost
            $t->decimal('line_total', 20, 4);
            $t->unsignedBigInteger('purchase_product_id')->nullable(); // the FIFO layer this line created
            $t->timestamps();
            $t->index('goods_receipt_id');
            $t->index(['product_id', 'variant']);
        });

        Schema::create('supplier_invoices', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('supplier_id');
            $t->unsignedBigInteger('goods_receipt_id')->nullable();
            $t->string('invoice_no', 80);
            $t->date('invoice_date');
            $t->decimal('subtotal', 20, 4);
            $t->decimal('discount', 20, 4)->default(0);
            $t->decimal('tax', 20, 4)->default(0);
            $t->decimal('freight', 20, 4)->default(0);
            $t->decimal('total', 20, 4);
            $t->decimal('paid', 20, 4)->default(0);
            $t->decimal('price_variance', 20, 4)->default(0);         // invoice total - goods received value, posted to the variance account
            $t->unsignedBigInteger('journal_entry_id')->nullable();
            $t->string('status', 12)->default('open');                // open | partial | paid | void
            $t->string('idempotency_key', 120)->unique();
            $t->unsignedInteger('created_by')->nullable();
            $t->timestamps();
            $t->unique(['supplier_id', 'invoice_no'], 'uq_supplier_invoice_no');
            $t->index('goods_receipt_id');
        });

        Schema::create('supplier_invoice_payments', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('supplier_id');
            $t->unsignedBigInteger('supplier_invoice_id')->nullable();   // null = unallocated supplier advance
            $t->unsignedBigInteger('account_id');
            $t->string('kind', 12);                                       // payment | advance | advance_applied
            $t->decimal('amount', 20, 4);
            $t->date('paid_on');
            $t->unsignedBigInteger('journal_entry_id')->nullable();
            $t->string('idempotency_key', 120)->unique();
            $t->unsignedInteger('created_by')->nullable();
            $t->timestamps();
            $t->index(['supplier_id', 'kind']);
            $t->index('supplier_invoice_id');
        });

        Schema::create('purchase_requirement_links', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('order_demand_id');
            $t->unsignedBigInteger('purchase_order_detail_id');
            $t->integer('qty_linked');
            $t->integer('qty_received')->default(0);
            $t->string('status', 12)->default('open');                    // open | received | cancelled
            $t->timestamps();
            $t->unique(['order_demand_id', 'purchase_order_detail_id'], 'uq_requirement_link');
            $t->index('purchase_order_detail_id');
        });

        Schema::create('stock_return_receipts', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('order_detail_id');
            $t->integer('qty');
            $t->string('condition', 12);                                   // resellable | damaged | rejected
            $t->unsignedBigInteger('stock_movement_id')->nullable();
            $t->string('note', 255)->nullable();
            $t->string('idempotency_key', 120)->unique();
            $t->unsignedInteger('received_by')->nullable();
            $t->timestamps();
            $t->index('order_detail_id');
        });

        Schema::create('journal_entries', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('entry_no', 40)->unique();
            $t->date('entry_date');
            $t->string('source_type', 40)->nullable();
            $t->unsignedBigInteger('source_id')->nullable();
            $t->string('description', 255)->nullable();
            $t->unsignedBigInteger('reversal_of_id')->nullable()->unique(); // an entry can be reversed once
            $t->string('idempotency_key', 120)->unique();
            $t->unsignedInteger('created_by')->nullable();
            $t->timestamp('posted_at')->nullable();
            $t->index(['source_type', 'source_id']);
            $t->index('entry_date');
        });

        Schema::create('journal_lines', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('journal_entry_id');
            $t->unsignedBigInteger('account_id');
            $t->decimal('debit', 20, 2)->default(0);
            $t->decimal('credit', 20, 2)->default(0);
            $t->unsignedBigInteger('supplier_id')->nullable();
            $t->string('memo', 255)->nullable();
            $t->index(['account_id', 'journal_entry_id']);
            $t->index('journal_entry_id');
            $t->index('supplier_id');
        });
    }

    public function down()
    {
        foreach (['journal_lines', 'journal_entries', 'stock_return_receipts', 'purchase_requirement_links', 'supplier_invoice_payments', 'supplier_invoices',
                  'goods_receipt_lines', 'goods_receipts', 'stock_allocations', 'stock_movements', 'order_demands', 'stock_balances', 'idempotency_keys'] as $table) {
            Schema::dropIfExists($table);
        }
    }
}
