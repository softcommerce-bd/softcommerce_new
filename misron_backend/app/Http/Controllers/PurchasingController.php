<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\Accounting\JournalService;
use App\Services\Accounting\Money;
use App\Services\Inventory\InventoryMode;
use App\Services\Inventory\InventoryReconciliation;
use App\Services\Inventory\StockBalance;
use App\Services\Purchasing\BulkPurchaseImporter;
use App\Services\Purchasing\GoodsReceiptService;
use App\Services\Purchasing\PurchaseOrderService;
use App\Services\Purchasing\PurchaseRequirementsService;
use App\Services\Purchasing\SupplierLedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Smart Purchase Manager screens. Thin: every number and every write comes from the services
 * (PurchaseRequirementsService, PurchaseOrderService, GoodsReceiptService, SupplierLedgerService, StockBalance).
 * Authentication is on the route group; the permission for each action is below.
 */
class PurchasingController extends Controller
{
    public function __construct()
    {
        $this->middleware(['permission:view_purchases'])->only(['dashboard', 'quickPurchase', 'searchProducts', 'supplierOutstanding']);
        $this->middleware(['permission:add_purchase'])->only(['quickSubmit', 'quickSupplier', 'bulkPreview', 'bulkCommit', 'bulkTemplate']);
        $this->middleware(['permission:view_purchase_requirements'])->only(['requirements']);
        $this->middleware(['permission:manage_purchase_orders'])->only(['createPoFromRequirements']);
        $this->middleware(['permission:receive_goods'])->only(['receivingIndex', 'receivingForm', 'receive']);
        $this->middleware(['permission:view_inventory_reports'])->only(['stockOverview', 'movements', 'backorders', 'valuation', 'report']);
        $this->middleware(['permission:manage_stock_policy'])->only(['savePolicy']);
        $this->middleware(['permission:manage_returns_receiving'])->only(['returns', 'receiveReturn']);
        $this->middleware(['permission:view_supplier_payments'])->only(['supplierPayments']);
        $this->middleware(['permission:add_purchase'])->only(['recordInvoice']);
        $this->middleware(['permission:make_supplier_payment'])->only(['payInvoice', 'supplierAdvance']);
        $this->middleware(['permission:manage_purchase_returns'])->only(['supplierReturns', 'returnToSupplier']);
    }

    private function ready(): ?\Illuminate\Http\RedirectResponse
    {
        if (!InventoryMode::installed()) {
            flash(translate('The inventory upgrade has not been installed yet (run the inventory_v2 migrations).'))->error();

            return redirect()->route('admin.dashboard');
        }

        return null;
    }

    public function dashboard()
    {
        if ($r = $this->ready()) {
            return $r;
        }
        $demand = (int) DB::table('stock_balances')->sum('demand');
        $allocated = (int) DB::table('stock_balances')->sum('allocated');
        $cards = [
            'awaiting_orders' => (int) DB::table('order_demands')->where('status', 'open')->whereRaw('qty_confirmed - qty_dispatched - qty_cancelled > qty_allocated')->distinct()->count('order_id'),
            'units_to_source' => count($items = (new PurchaseRequirementsService)->items(['only_shortage' => true])) ? array_sum(array_column($items, 'shortage')) : 0,
            'open_pos' => (int) DB::table('purchase_orders')->whereIn('status', ['ordered', 'partially_received'])->count(),
            'awaiting_receipt' => (int) DB::table('purchase_order_details as d')->join('purchase_orders as o', 'o.id', '=', 'd.purchase_order_id')->whereIn('o.status', ['ordered', 'partially_received'])->sum('d.pending_quantity'),
            'payables' => number_format((float) DB::table('supplier_invoices')->where('status', '!=', 'void')->selectRaw('COALESCE(SUM(total - paid),0) v')->value('v'), 2, '.', ''),
            'valuation' => number_format((float) DB::table('purchase_products')->selectRaw('COALESCE(SUM(remaining_qty * CAST(buying_unit_cost AS DECIMAL(20,4))),0) v')->value('v'), 2, '.', ''),
            'exceptions' => (int) DB::table('order_details')->where('cost_status', 'uncosted')->count(),
        ];
        $recent = DB::table('goods_receipts as g')->join('suppliers as s', 's.id', '=', 'g.supplier_id')->orderByDesc('g.id')->limit(8)->get(['g.id', 'g.receipt_no', 'g.received_at', 'g.total', 'g.invoice_status', 's.business_name']);

        return view('backend.purchasing.dashboard', compact('cards', 'recent', 'demand', 'allocated'));
    }

    public function requirements(Request $request)
    {
        if ($r = $this->ready()) {
            return $r;
        }
        $items = (new PurchaseRequirementsService)->items($request->only(['product_id', 'supplier_id', 'order_id', 'from', 'to']) + ['only_shortage' => $request->boolean('only_shortage')]);
        $suppliers = DB::table('suppliers')->orderBy('business_name')->get(['id', 'business_name']);

        return view('backend.purchasing.requirements', ['items' => $items, 'suppliers' => $suppliers, 'filters' => $request->all(), 'opKey' => (string) Str::uuid()]);
    }

    public function createPoFromRequirements(Request $request, PurchaseOrderService $pos)
    {
        $data = $request->validate(['op_key' => 'required|string|max:100', 'supplier_id' => 'required|integer|exists:suppliers,id', 'status' => 'nullable|in:draft,ordered',
            'items' => 'required|array|min:1', 'items.*.product_id' => 'required|integer', 'items.*.variant' => 'nullable|string|max:255', 'items.*.qty' => 'required|integer|min:1', 'items.*.unit_cost' => 'required|numeric|min:0']);
        $svc = new PurchaseRequirementsService;
        $lines = [];
        foreach ($data['items'] as $it) {
            $variant = StockBalance::key($it['variant'] ?? '');
            $demandIds = DB::table('order_demands')->where('product_id', $it['product_id'])->where('variant', $variant)->where('status', 'open')->orderBy('confirmed_at')->orderBy('id')->get()
                ->filter(fn ($d) => PurchaseRequirementsService::uncoveredForDemand($d) > 0)->pluck('id')->all();
            if ($demandIds === []) { // everything this item needs is already in stock or on order: buying it from here would count the same units twice
                flash(translate('Already covered by stock or an open purchase order. Nothing was ordered.'))->warning();

                return back()->withInput();
            }
            $lines[] = ['product_id' => (int) $it['product_id'], 'variant' => $variant, 'qty' => (int) $it['qty'], 'unit_cost' => $it['unit_cost'], 'demand_ids' => $demandIds];
        }
        try {
            $po = $pos->create('req:' . $data['op_key'], ['supplier_id' => $data['supplier_id'], 'status' => $data['status'] ?? 'ordered', 'lines' => $lines, 'note' => 'Created from confirmed-order requirements'], $request->user()->id);
        } catch (InvalidArgumentException $e) {
            flash($e->getMessage())->error();

            return back()->withInput();
        }
        flash(translate('Purchase order created: ') . $po['reference_no'])->success();

        return redirect()->route('purchasing.receiving.index');
    }

    public function receivingIndex()
    {
        if ($r = $this->ready()) {
            return $r;
        }
        $pos = DB::table('purchase_orders as o')->join('suppliers as s', 's.id', '=', 'o.supplier_id')->whereIn('o.status', ['ordered', 'partially_received'])->orderByDesc('o.id')
            ->get(['o.id', 'o.reference_no', 'o.purchase_date', 'o.status', 'o.net_total_amount', 's.business_name']);

        return view('backend.purchasing.receiving_index', compact('pos'));
    }

    public function receivingForm($id)
    {
        $po = DB::table('purchase_orders')->where('id', $id)->first() ?: abort(404);
        $lines = DB::table('purchase_order_details')->where('purchase_order_id', $id)->get();
        $supplier = DB::table('suppliers')->where('id', $po->supplier_id)->first(['id', 'business_name']);
        $cash = DB::table('chart_of_accounts')->whereIn('sub_type', ['cash', 'bank', 'mobile_bank'])->where('status', 'active')->where('is_deleted', '0')->orderBy('name')->get(['id', 'name', 'current_balance']);

        return view('backend.purchasing.receiving_form', ['po' => $po, 'lines' => $lines, 'supplier' => $supplier, 'accounts' => $cash, 'opKey' => (string) Str::uuid()]);
    }

    /** Receive (and optionally invoice and pay). Each step is its own transaction with its own derived key, so a failure later leaves the earlier steps traceable and a retry never repeats them. */
    public function receive(Request $request, $id, GoodsReceiptService $grn, SupplierLedgerService $ledger)
    {
        $v = $request->validate(['op_key' => 'required|string|max:100', 'lines' => 'required|array', 'lines.*.detail_id' => 'required|integer', 'lines.*.qty' => 'nullable|integer|min:0', 'lines.*.unit_cost' => 'required|numeric|min:0',
            'freight' => 'nullable|numeric|min:0', 'freight_account_id' => 'nullable|integer', 'invoice_no' => 'nullable|string|max:80', 'pay_amount' => 'nullable|numeric|min:0', 'pay_account_id' => 'nullable|integer']);
        $po = DB::table('purchase_orders')->where('id', $id)->first() ?: abort(404);
        $lines = [];
        foreach ($v['lines'] as $l) {
            if ((int) ($l['qty'] ?? 0) < 1) {
                continue;
            }
            $d = DB::table('purchase_order_details')->where('id', $l['detail_id'])->where('purchase_order_id', $id)->first() ?: abort(422);
            $lines[] = ['purchase_order_detail_id' => $d->id, 'product_id' => $d->product_id, 'variant' => $d->variant ?? '', 'qty' => (int) $l['qty'], 'unit_cost' => $l['unit_cost']];
        }
        if (!$lines) {
            flash(translate('Enter a received quantity for at least one line.'))->error();

            return back();
        }
        $by = $request->user()->id;
        try {
            $data = ['supplier_id' => $po->supplier_id, 'purchase_order_id' => $po->id, 'lines' => $lines];
            if (!empty($v['freight']) && (float) $v['freight'] > 0) {
                $data['landed_costs'] = ['amount' => $v['freight'], 'account_id' => $v['freight_account_id'] ?? 0];
            }
            $r = $grn->receive($v['op_key'] . '-grn', $data, $by);
            $msg = translate('Goods received: ') . $r['receipt_no'];
            if (!empty($v['invoice_no'])) {
                $goods = DB::table('goods_receipts')->where('id', $r['goods_receipt_id'])->value('goods_total');
                $inv = $ledger->recordInvoice($v['op_key'] . '-inv', ['supplier_id' => $po->supplier_id, 'goods_receipt_id' => $r['goods_receipt_id'], 'invoice_no' => $v['invoice_no'], 'subtotal' => $goods], $by);
                $msg .= ' · ' . translate('invoice recorded');
                if (!empty($v['pay_amount']) && (float) $v['pay_amount'] > 0) {
                    $this->authorizeOrFail($request, 'make_supplier_payment');
                    $ledger->pay($v['op_key'] . '-pay', $inv['supplier_invoice_id'], (int) ($v['pay_account_id'] ?? 0), $v['pay_amount'], null, $by);
                    $msg .= ' · ' . translate('payment recorded');
                }
            }
            flash($msg)->success();
        } catch (InvalidArgumentException $e) {
            flash($e->getMessage())->error();

            return back()->withInput();
        }

        return redirect()->route('purchasing.receiving.index');
    }

    private function authorizeOrFail(Request $request, string $permission): void
    {
        abort_unless($request->user()->can($permission), 403);
    }

    // ── Quick Purchase ────────────────────────────────────────────────────

    public function quickPurchase()
    {
        if ($r = $this->ready()) {
            return $r;
        }
        $suppliers = DB::table('suppliers')->orderBy('business_name')->get(['id', 'business_name', 'due_amount', 'opening_balance']);
        $accounts = DB::table('chart_of_accounts')->whereIn('sub_type', ['cash', 'bank', 'mobile_bank'])->where('status', 'active')->where('is_deleted', '0')->orderBy('name')->get(['id', 'name', 'current_balance']);

        return view('backend.purchasing.quick', compact('suppliers', 'accounts'));
    }

    public function searchProducts(Request $request)
    {
        $q = trim((string) $request->query('q'));
        if (mb_strlen($q) < 1) {
            return response()->json([]);
        }
        $like = '%' . addcslashes($q, '%_\\') . '%';
        $rows = DB::table('product_stocks as s')->join('products as p', 'p.id', '=', 's.product_id')
            ->where(fn ($w) => $w->where('p.name', 'like', $like)->orWhere('s.sku', 'like', $like)->orWhere('p.barcode', $q)->orWhere('s.variant', 'like', $like)->when(ctype_digit($q), fn ($x) => $x->orWhere('p.id', (int) $q)))
            ->orderBy('p.name')->limit(20)->get(['p.id', 'p.name', 'p.thumbnail_img', 'p.preferred_supplier_id', 'p.estimated_purchase_cost', 's.variant', 's.sku']);
        $out = [];
        foreach ($rows as $r) {
            $snap = StockBalance::snapshot((int) $r->id, $r->variant);
            $last = DB::table('purchase_products')->where('product_id', $r->id)->where(fn ($w) => $w->where('variant', $r->variant)->orWhere(fn ($x) => $r->variant === '' ? $x->whereNull('variant') : $x->whereRaw('1 = 0')))->orderByDesc('id')->value('buying_unit_cost');
            $out[] = ['product_id' => $r->id, 'name' => $r->name, 'variant' => $r->variant, 'sku' => $r->sku, 'thumb' => $r->thumbnail_img ? uploaded_asset($r->thumbnail_img) : null,
                'on_hand' => $snap['on_hand'], 'available' => $snap['available'], 'demand' => $snap['demand'], 'incoming' => $snap['incoming'], 'last_cost' => $last, 'estimated_cost' => $r->estimated_purchase_cost, 'preferred_supplier_id' => $r->preferred_supplier_id,
                'shortage' => max(0, $snap['demand'] - $snap['allocated'] - $snap['incoming'])];
        }

        return response()->json($out);
    }

    public function quickSupplier(Request $request)
    {
        $d = $request->validate(['business_name' => 'required|string|max:190', 'mobile' => 'nullable|string|max:30']);
        $id = DB::table('suppliers')->insertGetId(['slug' => Str::slug($d['business_name']) . '-' . Str::lower(Str::random(5)), 'business_name' => $d['business_name'], 'contact_person_name' => $d['business_name'], 'mobile' => $d['mobile'] ?? '', 'opening_balance' => '0', 'due_amount' => 0, 'paid_amount' => 0, 'created_at' => now(), 'updated_at' => now()]);

        return response()->json(['id' => $id, 'business_name' => $d['business_name']], 201);
    }

    /** draft | order | receive | receive_pay  — each is a separate, idempotent backend operation. */
    public function quickSubmit(Request $request, PurchaseOrderService $pos, GoodsReceiptService $grn, SupplierLedgerService $ledger)
    {
        $v = $request->validate(['op_key' => 'required|string|max:100', 'action' => 'required|in:draft,order,receive,receive_pay', 'supplier_id' => 'required|integer|exists:suppliers,id',
            'lines' => 'required|array|min:1|max:200', 'lines.*.product_id' => 'required|integer', 'lines.*.variant' => 'nullable|string|max:255', 'lines.*.qty' => 'required|integer|min:1',
            'lines.*.unit_cost' => 'required|numeric|min:0', 'lines.*.discount' => 'nullable|numeric|min:0', 'freight' => 'nullable|numeric|min:0', 'other_cost' => 'nullable|numeric|min:0', 'cost_account_id' => 'nullable|integer',
            'invoice_no' => 'nullable|string|max:80', 'paid_amount' => 'nullable|numeric|min:0', 'pay_account_id' => 'nullable|integer', 'note' => 'nullable|string|max:500']);
        $by = $request->user()->id;
        $lines = [];
        foreach ($v['lines'] as $i => $l) {
            $qty = (int) $l['qty'];
            $cost = Money::micro($l['unit_cost']);
            $disc = Money::micro($l['discount'] ?? 0);
            if ($disc > $qty * $cost) {
                return response()->json(['message' => 'Line ' . ($i + 1) . ': the discount is more than the line value.'], 422);
            }
            $net = intdiv(($qty * $cost - $disc) * 2 + $qty, 2 * $qty);
            $lines[] = ['product_id' => (int) $l['product_id'], 'variant' => StockBalance::key($l['variant'] ?? ''), 'qty' => $qty, 'unit_cost' => Money::decimal($net)];
        }
        // Duplicate product+variation rows are almost always a slip: refuse instead of silently double buying.
        $keys = array_map(fn ($l) => $l['product_id'] . '|' . $l['variant'], $lines);
        if (count($keys) !== count(array_unique($keys))) {
            return response()->json(['message' => 'The same product and variation appears twice: merge the rows.'], 422);
        }
        try {
            if (in_array($v['action'], ['draft', 'order'], true)) {
                $po = $pos->create($v['op_key'], ['supplier_id' => $v['supplier_id'], 'status' => $v['action'] === 'draft' ? 'draft' : 'ordered', 'lines' => $lines, 'note' => $v['note'] ?? null], $by);

                return response()->json(['ok' => true, 'message' => $v['action'] === 'draft' ? 'Draft saved: ' . $po['reference_no'] : 'Purchase order created: ' . $po['reference_no'], 'reference' => $po['reference_no']]);
            }
            $landed = (float) ($v['freight'] ?? 0) + (float) ($v['other_cost'] ?? 0);
            $data = ['supplier_id' => $v['supplier_id'], 'lines' => $lines, 'note' => $v['note'] ?? null];
            if ($landed > 0) {
                $data['landed_costs'] = ['amount' => number_format($landed, 4, '.', ''), 'account_id' => $v['cost_account_id'] ?? 0];
            }
            $r = $grn->receive($v['op_key'] . '-grn', $data, $by);
            $msg = 'Goods received: ' . $r['receipt_no'];
            $goods = DB::table('goods_receipts')->where('id', $r['goods_receipt_id'])->value('goods_total');
            $inv = $ledger->recordInvoice($v['op_key'] . '-inv', ['supplier_id' => $v['supplier_id'], 'goods_receipt_id' => $r['goods_receipt_id'], 'invoice_no' => ($v['invoice_no'] ?? null) ?: 'INV-' . $r['receipt_no'], 'subtotal' => $goods], $by);
            $msg .= ' · invoice recorded';
            if ($v['action'] === 'receive_pay' && (float) ($v['paid_amount'] ?? 0) > 0) {
                $this->authorizeOrFail($request, 'make_supplier_payment');
                $ledger->pay($v['op_key'] . '-pay', $inv['supplier_invoice_id'], (int) ($v['pay_account_id'] ?? 0), $v['paid_amount'], null, $by);
                $msg .= ' · payment recorded';
            }

            return response()->json(['ok' => true, 'message' => $msg, 'reference' => $r['receipt_no']]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // ── Bulk CSV ──────────────────────────────────────────────────────────

    public function bulkTemplate()
    {
        return response(BulkPurchaseImporter::TEMPLATE, 200, ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="bulk-purchase-template.csv"']);
    }

    public function bulkPreview(Request $request, BulkPurchaseImporter $bulk)
    {
        $request->validate(['supplier_id' => 'required|integer', 'file' => 'required|file|max:2048|mimes:csv,txt']);
        try {
            return response()->json($bulk->preview((int) $request->supplier_id, (string) file_get_contents($request->file('file')->getRealPath())));
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function bulkCommit(Request $request, BulkPurchaseImporter $bulk)
    {
        $request->validate(['supplier_id' => 'required|integer', 'file' => 'required|file|max:2048|mimes:csv,txt', 'status' => 'nullable|in:draft,ordered']);
        try {
            $r = $bulk->commit((int) $request->supplier_id, (string) file_get_contents($request->file('file')->getRealPath()), $request->input('status', 'draft'), $request->user()->id);

            return response()->json(['ok' => true, 'message' => ($r['replayed'] ? 'Already imported earlier: ' : 'Purchase order created: ') . $r['reference_no']] + $r);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // ── Inventory views and reports ───────────────────────────────────────

    public function stockOverview(Request $request)
    {
        if ($r = $this->ready()) {
            return $r;
        }
        $q = DB::table('product_stocks as s')->join('products as p', 'p.id', '=', 's.product_id')->leftJoin('stock_balances as b', fn ($j) => $j->on('b.product_id', '=', 's.product_id')->on('b.variant', '=', 's.variant'))
            ->select('p.id', 'p.name', 'p.stock_policy', 'p.max_backorder_qty', 'p.max_preorder_qty', 'p.preorder_available_at', 'p.preferred_supplier_id', 'p.estimated_purchase_cost', 'p.supplier_lead_time_days', 's.variant', 's.sku', 's.qty as on_hand',
                DB::raw('COALESCE(b.demand,0) as demand'), DB::raw('COALESCE(b.allocated,0) as allocated'), DB::raw('COALESCE(b.damaged,0) as damaged'), DB::raw('s.qty - COALESCE(b.demand,0) as available'));
        if ($term = trim((string) $request->query('q'))) {
            $like = '%' . addcslashes($term, '%_\\') . '%';
            $q->where(fn ($w) => $w->where('p.name', 'like', $like)->orWhere('s.sku', 'like', $like)->orWhere('p.barcode', $term));
        }
        if ($request->boolean('negative')) {
            $q->whereRaw('s.qty - COALESCE(b.demand,0) < 0');
        }
        $rows = $q->orderBy('p.name')->paginate(30)->withQueryString();
        $suppliers = DB::table('suppliers')->orderBy('business_name')->get(['id', 'business_name']);

        return view('backend.purchasing.stock', compact('rows', 'suppliers'));
    }

    public function savePolicy(Request $request, $id)
    {
        $d = $request->validate(['stock_policy' => 'required|in:stock_only,backorder,preorder', 'max_backorder_qty' => 'nullable|integer|min:0', 'max_preorder_qty' => 'nullable|integer|min:0', 'preorder_available_at' => 'nullable|date',
            'preferred_supplier_id' => 'nullable|integer|exists:suppliers,id', 'estimated_purchase_cost' => 'nullable|numeric|min:0', 'supplier_lead_time_days' => 'nullable|integer|min:0|max:365']);
        $n = DB::table('products')->where('id', $id)->update(array_map(fn ($v) => $v === '' ? null : $v, $d) + ['updated_at' => now()]);
        abort_if(!$n && !DB::table('products')->where('id', $id)->exists(), 404);
        flash(translate('Inventory settings saved.'))->success();

        return back();
    }

    public function movements(Request $request)
    {
        $q = DB::table('stock_movements as m')->join('products as p', 'p.id', '=', 'm.product_id')->orderByDesc('m.id');
        if ($request->filled('product_id')) {
            $q->where('m.product_id', (int) $request->product_id);
        }
        if ($request->filled('type')) {
            $q->where('m.type', $request->type);
        }
        $rows = $q->select('m.id', 'm.created_at', 'p.name', 'm.variant', 'm.type', 'm.on_hand_delta', 'm.demand_delta', 'm.allocated_delta', 'm.source_type', 'm.source_id', 'm.note')->paginate(50)->withQueryString();

        return view('backend.purchasing.table', ['title' => translate('Stock movements'), 'columns' => ['id' => '#', 'created_at' => 'When', 'name' => 'Product', 'variant' => 'Variation', 'type' => 'Type', 'on_hand_delta' => 'Physical Δ', 'demand_delta' => 'Demand Δ', 'allocated_delta' => 'Allocated Δ', 'source_type' => 'Source', 'source_id' => 'Ref', 'note' => 'Note'], 'rows' => $rows]);
    }

    public function backorders()
    {
        $rows = DB::table('stock_balances as b')->join('products as p', 'p.id', '=', 'b.product_id')->join('product_stocks as s', fn ($j) => $j->on('s.product_id', '=', 'b.product_id')->on('s.variant', '=', 'b.variant'))
            ->whereRaw('s.qty - b.demand < 0')->orderByRaw('(s.qty - b.demand)')->paginate(50, ['p.name', 'b.variant', 's.qty as on_hand', 'b.demand', 'b.allocated', DB::raw('s.qty - b.demand as available')]);

        return view('backend.purchasing.table', ['title' => translate('Confirmed backorders (available below zero)'), 'columns' => ['name' => 'Product', 'variant' => 'Variation', 'on_hand' => 'Physical', 'demand' => 'Confirmed demand', 'allocated' => 'Allocated', 'available' => 'Available to sell'], 'rows' => $rows]);
    }

    public function valuation()
    {
        $rows = DB::table('purchase_products as l')->join('products as p', 'p.id', '=', 'l.product_id')->where('l.remaining_qty', '>', 0)->groupBy('p.id', 'p.name', 'l.variant')
            ->selectRaw("p.name, COALESCE(l.variant,'') variant, SUM(l.remaining_qty) qty, ROUND(SUM(l.remaining_qty * CAST(l.buying_unit_cost AS DECIMAL(20,4))),2) value")->orderByDesc('value')->paginate(50);
        $total = DB::table('purchase_products')->selectRaw('ROUND(COALESCE(SUM(remaining_qty * CAST(buying_unit_cost AS DECIMAL(20,4))),0),2) v')->value('v');

        return view('backend.purchasing.table', ['title' => translate('Inventory valuation (FIFO cost layers)') . ' — ' . $total, 'columns' => ['name' => 'Product', 'variant' => 'Variation', 'qty' => 'Units', 'value' => 'FIFO value'], 'rows' => $rows]);
    }

    public function supplierOutstanding()
    {
        $rows = DB::table('supplier_invoices as i')->join('suppliers as s', 's.id', '=', 'i.supplier_id')->where('i.status', '!=', 'void')->groupBy('s.id', 's.business_name')
            ->selectRaw('s.business_name, COUNT(*) invoices, ROUND(SUM(i.total),2) invoiced, ROUND(SUM(i.paid),2) paid, ROUND(SUM(i.total - i.paid),2) outstanding')->orderByDesc('outstanding')->paginate(50);

        return view('backend.purchasing.table', ['title' => translate('Supplier outstanding'), 'columns' => ['business_name' => 'Supplier', 'invoices' => 'Invoices', 'invoiced' => 'Invoiced', 'paid' => 'Paid', 'outstanding' => 'Outstanding'], 'rows' => $rows]);
    }

    /** Named reports. Everything here is read-only. */
    public function report(Request $request, string $name)
    {
        $d = $request->query('date', now()->toDateString());
        switch ($name) {
            case 'trial-balance':
                $tb = JournalService::trialBalance($d);
                $rows = collect($tb['rows'])->map(fn ($r) => (object) ['name' => $r->name, 'type' => $r->type, 'debit' => number_format($r->debit, 2, '.', ''), 'credit' => number_format($r->credit, 2, '.', '')])
                    ->push((object) ['name' => 'TOTAL', 'type' => $tb['balanced'] ? 'balanced' : 'NOT BALANCED', 'debit' => $tb['debit'], 'credit' => $tb['credit']]);

                return view('backend.purchasing.table', ['title' => translate('Trial balance (journal) as of ') . $d, 'columns' => ['name' => 'Account', 'type' => 'Type', 'debit' => 'Debit', 'credit' => 'Credit'], 'rows' => $rows]);
            case 'general-ledger':
                $acc = (int) $request->query('account_id');
                $from = $request->query('from');
                $to = $request->query('to', $d);
                $account = $acc ? DB::table('chart_of_accounts')->where('id', $acc)->first() : null;
                $debitNormal = $account ? in_array($account->type, ['asset', 'expense'], true) : true;
                $running = 0.0;
                if ($acc && $from) { // opening = journal movement before the start date
                    $o = DB::table('journal_lines as l')->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')->where('l.account_id', $acc)->where('e.entry_date', '<', $from)->selectRaw('COALESCE(SUM(l.debit),0) d, COALESCE(SUM(l.credit),0) c')->first();
                    $running = $debitNormal ? $o->d - $o->c : $o->c - $o->d;
                }
                $lines = DB::table('journal_lines as l')->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')->join('chart_of_accounts as a', 'a.id', '=', 'l.account_id')->when($acc, fn ($q) => $q->where('l.account_id', $acc))
                    ->when($from, fn ($q) => $q->where('e.entry_date', '>=', $from))->where('e.entry_date', '<=', $to)->orderBy('e.entry_date')->orderBy('e.id')->orderBy('l.id')->limit(2000)
                    ->get(['e.entry_date', 'e.entry_no', 'e.source_type', 'a.name as account', 'a.type', 'l.debit', 'l.credit', 'l.memo', 'l.mirrored']);
                $rows = $lines->map(function ($l) use (&$running, $acc, $debitNormal) {
                    $running += $debitNormal ? $l->debit - $l->credit : $l->credit - $l->debit;

                    return (object) ['entry_date' => $l->entry_date, 'entry_no' => $l->entry_no, 'source_type' => $l->source_type . ($l->mirrored ? ' (legacy, mirrored)' : ''), 'account' => $l->account, 'debit' => $l->debit, 'credit' => $l->credit, 'balance' => $acc ? number_format($running, 2, '.', '') : '', 'memo' => $l->memo];
                });

                return view('backend.purchasing.table', ['title' => translate('General ledger (journal)'), 'note' => translate('Journal entries only: history before journalling began is in the account statements. Balance column is running, in the account\'s own sign, and appears when one account is chosen.'),
                    'filters' => ['account_id' => ['Account ID', $acc ?: ''], 'from' => ['From (YYYY-MM-DD)', $from], 'to' => ['To (YYYY-MM-DD)', $to]],
                    'columns' => ['entry_date' => 'Date', 'entry_no' => 'Entry', 'source_type' => 'Source', 'account' => 'Account', 'debit' => 'Debit', 'credit' => 'Credit', 'balance' => 'Balance', 'memo' => 'Memo'], 'rows' => $rows]);
            case 'supplier-statement':
                $sid = (int) $request->query('supplier_id');
                $supplier = $sid ? DB::table('suppliers')->where('id', $sid)->first() : null;
                $ev = collect();
                if ($supplier) {
                    foreach (DB::table('supplier_invoices')->where('supplier_id', $sid)->get() as $i) {
                        $ev->push(['date' => $i->invoice_date, 'ref' => 'Invoice ' . $i->invoice_no, 'debit' => 0, 'credit' => (float) $i->total]);
                    }
                    foreach (DB::table('supplier_invoice_payments')->where('supplier_id', $sid)->get() as $pay) {
                        $ev->push(['date' => $pay->paid_on, 'ref' => ucfirst(str_replace('_', ' ', $pay->kind)), 'debit' => $pay->kind === 'advance' ? 0 : (float) $pay->amount, 'credit' => 0]);
                    }
                    foreach (DB::table('purchases')->where('supplier_id', $sid)->whereNotExists(fn ($q) => $q->selectRaw('1')->from('goods_receipts as g')->whereColumn('g.purchase_id', 'purchases.id'))->get() as $pu) {
                        $ev->push(['date' => $pu->purchase_date, 'ref' => 'Legacy purchase ' . $pu->reference_no, 'debit' => 0, 'credit' => (float) $pu->total_payment_due + (float) $pu->total_paid - (float) $pu->opening_balance_used]);
                        if ((float) $pu->total_paid > 0) {
                            $ev->push(['date' => $pu->purchase_date, 'ref' => 'Paid with ' . $pu->reference_no, 'debit' => (float) $pu->total_paid, 'credit' => 0]);
                        }
                    }
                }
                $run = 0.0;
                $rows = $ev->sortBy('date')->values()->map(function ($e) use (&$run) {
                    $run += $e['credit'] - $e['debit'];

                    return (object) ['date' => $e['date'], 'ref' => $e['ref'], 'debit' => number_format($e['debit'], 2, '.', ''), 'credit' => number_format($e['credit'], 2, '.', ''), 'balance' => number_format($run, 2, '.', '')];
                });
                if ($supplier) {
                    $rows->push((object) ['date' => '', 'ref' => 'Statement balance / supplier record due_amount', 'debit' => '', 'credit' => '', 'balance' => number_format($run, 2, '.', '') . ' / ' . number_format((float) $supplier->due_amount, 2, '.', '')]);
                }

                return view('backend.purchasing.table', ['title' => translate('Supplier statement') . ($supplier ? ': ' . $supplier->business_name : ''), 'note' => translate('Credit = owed to the supplier (invoices, legacy purchases); debit = paid or credited. Goods received but not yet invoiced are not a payable yet.'),
                    'filters' => ['supplier_id' => ['Supplier ID', $sid ?: '']], 'columns' => ['date' => 'Date', 'ref' => 'Document', 'debit' => 'Paid / credited', 'credit' => 'Owed', 'balance' => 'Balance'], 'rows' => $rows]);
            case 'goods-receiving':
                $rows = DB::table('goods_receipts as g')->join('suppliers as s', 's.id', '=', 'g.supplier_id')->orderByDesc('g.id')->paginate(50, ['g.receipt_no', 'g.received_at', 's.business_name', 'g.goods_total', 'g.landed_cost', 'g.total', 'g.invoice_status']);

                return view('backend.purchasing.table', ['title' => translate('Goods receiving'), 'columns' => ['receipt_no' => 'Receipt', 'received_at' => 'Date', 'business_name' => 'Supplier', 'goods_total' => 'Goods', 'landed_cost' => 'Landed cost', 'total' => 'Total', 'invoice_status' => 'Invoice'], 'rows' => $rows]);
            case 'purchase-orders':
                $rows = DB::table('purchase_orders as o')->join('suppliers as s', 's.id', '=', 'o.supplier_id')->orderByDesc('o.id')->paginate(50, ['o.reference_no', 'o.purchase_date', 's.business_name', 'o.status', 'o.total_item', 'o.net_total_amount']);

                return view('backend.purchasing.table', ['title' => translate('Purchase orders'), 'columns' => ['reference_no' => 'PO', 'purchase_date' => 'Date', 'business_name' => 'Supplier', 'status' => 'Status', 'total_item' => 'Units', 'net_total_amount' => 'Value'], 'rows' => $rows]);
            case 'pending-requirements':
                $items = (new PurchaseRequirementsService)->items(['only_shortage' => true]);

                return view('backend.purchasing.table', ['title' => translate('Pending purchase requirements'), 'columns' => ['product_name' => 'Product', 'variant' => 'Variation', 'demand' => 'Confirmed demand', 'on_hand' => 'Physical', 'incoming' => 'Incoming', 'shortage' => 'To buy'], 'rows' => collect($items)->map(fn ($i) => (object) $i)]);
            case 'actual-order-profit':
                $from = $request->query('from');
                $to = $request->query('to');
                $base = fn () => DB::table('order_details as d')->join('orders as o', 'o.id', '=', 'd.order_id')->where('d.stock_mode', 'v2')
                    ->when($from, fn ($q) => $q->whereDate('o.created_at', '>=', $from))->when($to, fn ($q) => $q->whereDate('o.created_at', '<=', $to));
                $sum = $base()->where('d.cost_status', 'final')->selectRaw('COALESCE(SUM(d.price),0) sales, COALESCE(SUM(d.quantity * d.buying_price),0) cost, COALESCE(SUM(d.profit),0) profit, COUNT(*) n')->first();
                $pending = $base()->where('d.cost_status', '<>', 'final')->selectRaw("COUNT(*) n, COALESCE(SUM(d.price),0) sales, SUM(d.cost_status = 'uncosted') uncosted")->first();
                $est = $base()->where('d.cost_status', 'uncosted')->whereNotNull('d.estimated_unit_cost')->selectRaw('COALESCE(SUM(d.price - d.quantity * d.estimated_unit_cost),0) p, COUNT(*) n')->first();
                $rows = $base()->orderByDesc('d.id')->paginate(50, ['o.code', 'd.product_id', 'd.quantity', 'd.price', 'd.buying_price', 'd.profit', 'd.cost_status', 'd.estimated_unit_cost']);
                $rows->getCollection()->transform(function ($r) {
                    $final = $r->cost_status === 'final';
                    $r->profit = $final ? $r->profit : ($r->estimated_unit_cost !== null ? 'ESTIMATE ' . number_format($r->price - $r->quantity * $r->estimated_unit_cost, 2, '.', '') . ' (not final)' : 'not final');
                    $r->buying_price = $final ? $r->buying_price : 'unknown';
                    $r->cost_status = $final ? 'FINAL (FIFO cost)' : strtoupper((string) $r->cost_status) . ' - excluded from profit';

                    return $r;
                });

                return view('backend.purchasing.table', ['title' => translate('Order profit'), 'filters' => ['from' => ['From (YYYY-MM-DD)', $from], 'to' => ['To (YYYY-MM-DD)', $to]],
                    'note' => translate('FINAL profit (actual FIFO cost recorded at dispatch): ') . number_format($sum->profit, 2) . ' ' . translate('on sales of') . ' ' . number_format($sum->sales, 2) . ' (' . $sum->n . ' ' . translate('lines') . '). '
                        . translate('NOT final, excluded from the profit above: ') . (int) $pending->n . ' ' . translate('lines with sales of') . ' ' . number_format($pending->sales, 2) . ' (' . (int) $pending->uncosted . ' ' . translate('with unknown cost') . '). ' . translate('ESTIMATED profit of lines that have only a planning cost (kept separate, never booked): ') . number_format($est->p, 2) . ' (' . (int) $est->n . ' ' . translate('lines') . ').',
                    'columns' => ['code' => 'Order', 'product_id' => 'Product', 'quantity' => 'Qty', 'price' => 'Sold for', 'buying_price' => 'Unit cost', 'profit' => 'Profit', 'cost_status' => 'Cost'], 'rows' => $rows]);
            case 'reconciliation':
                $rep = collect((new InventoryReconciliation)->report())->map(fn ($r) => (object) ['severity' => $r['severity'], 'label' => $r['label'], 'count' => $r['count'], 'samples' => implode('; ', $r['samples'])]);

                return view('backend.purchasing.table', ['title' => translate('Reconciliation (read-only)'), 'columns' => ['severity' => 'Severity', 'label' => 'Check', 'count' => 'Result', 'samples' => 'Examples'], 'rows' => $rep]);
        }
        abort(404);
    }

    // ── Customer returns: goods are received back and inspected before anything becomes stock ──

    public function returns()
    {
        if ($r = $this->ready()) {
            return $r;
        }
        $rows = DB::table('order_demands as d')->join('order_details as l', 'l.id', '=', 'd.order_detail_id')->join('orders as o', 'o.id', '=', 'd.order_id')->join('products as p', 'p.id', '=', 'd.product_id')
            ->where('d.qty_dispatched', '>', 0)->orderByDesc('d.id')->limit(100)
            ->get(['l.id as line_id', 'o.code', 'p.name', 'd.variant', 'd.qty_dispatched', DB::raw('(SELECT COALESCE(SUM(qty),0) FROM stock_return_receipts r WHERE r.order_detail_id = l.id) as returned')]);

        return view('backend.purchasing.returns', ['rows' => $rows]);
    }

    public function receiveReturn(Request $request, \App\Services\Inventory\DemandService $demand)
    {
        $v = $request->validate(['op_key' => 'required|string|max:100', 'order_detail_id' => 'required|integer', 'qty' => 'required|integer|min:1', 'condition' => 'required|in:resellable,damaged,rejected', 'note' => 'nullable|string|max:255']);
        $line = \App\Models\OrderDetail::findOrFail($v['order_detail_id']);
        try {
            $demand->receiveReturn($line, (int) $v['qty'], $v['condition'], 'ret:' . $v['op_key'], $request->user()->id, $v['note'] ?? null);
            flash(translate('Return received as ') . $v['condition'])->success();
        } catch (InvalidArgumentException $e) {
            flash($e->getMessage())->error();
        }

        return back();
    }

    // ── Supplier invoices, payments, advances ─────────────────────────────

    public function supplierPayments(Request $request)
    {
        if ($r = $this->ready()) {
            return $r;
        }
        $suppliers = DB::table('suppliers')->orderBy('business_name')->get(['id', 'business_name', 'due_amount', 'opening_balance']);
        $sid = (int) $request->query('supplier_id');
        $supplier = $sid ? $suppliers->firstWhere('id', $sid) : null;
        $uninvoiced = $supplier ? DB::table('goods_receipts')->where('supplier_id', $sid)->where('status', 'posted')->where('invoice_status', '<>', 'invoiced')->orderBy('id')->get(['id', 'receipt_no', 'received_at', 'goods_total']) : collect();
        $invoices = $supplier ? DB::table('supplier_invoices')->where('supplier_id', $sid)->where('status', '<>', 'void')->orderByDesc('id')->limit(100)->get() : collect();
        $accounts = DB::table('chart_of_accounts')->whereIn('sub_type', ['cash', 'bank', 'mobile_bank'])->where('status', 'active')->where('is_deleted', '0')->orderBy('name')->get(['id', 'name', 'current_balance']);

        return view('backend.purchasing.payments', compact('suppliers', 'supplier', 'uninvoiced', 'invoices', 'accounts'));
    }

    public function recordInvoice(Request $request, SupplierLedgerService $ledger)
    {
        $v = $request->validate(['op_key' => 'required|string|max:100', 'supplier_id' => 'required|integer|exists:suppliers,id', 'goods_receipt_id' => 'required|integer', 'invoice_no' => 'required|string|max:80',
            'subtotal' => 'required|numeric|gt:0', 'discount' => 'nullable|numeric|min:0', 'tax' => 'nullable|numeric|min:0', 'freight' => 'nullable|numeric|min:0', 'invoice_date' => 'nullable|date']);
        try {
            $r = $ledger->recordInvoice('inv:' . $v['op_key'], $v, $request->user()->id);
            flash(($r['replayed'] ?? false) ? translate('This invoice was already recorded.') : translate('Supplier invoice recorded.'))->success();
        } catch (InvalidArgumentException $e) {
            flash($e->getMessage())->error();
        }

        return redirect()->route('purchasing.supplier.payments', ['supplier_id' => $v['supplier_id']]);
    }

    public function payInvoice(Request $request, $id, SupplierLedgerService $ledger)
    {
        $v = $request->validate(['op_key' => 'required|string|max:100', 'account_id' => 'required|integer|exists:chart_of_accounts,id', 'amount' => 'required|numeric|gt:0', 'paid_on' => 'nullable|date']);
        $invoice = DB::table('supplier_invoices')->where('id', $id)->first() ?: abort(404);
        try {
            $r = $ledger->pay('pay:' . $v['op_key'], (int) $id, (int) $v['account_id'], $v['amount'], $v['paid_on'] ?? null, $request->user()->id);
            flash(($r['replayed'] ?? false) ? translate('This payment was already recorded.') : translate('Payment recorded.'))->success();
        } catch (InvalidArgumentException $e) {
            flash($e->getMessage())->error();
        }

        return redirect()->route('purchasing.supplier.payments', ['supplier_id' => $invoice->supplier_id]);
    }

    public function supplierAdvance(Request $request, SupplierLedgerService $ledger)
    {
        $v = $request->validate(['op_key' => 'required|string|max:100', 'supplier_id' => 'required|integer|exists:suppliers,id', 'account_id' => 'required|integer|exists:chart_of_accounts,id', 'amount' => 'required|numeric|gt:0']);
        try {
            $ledger->advance('adv:' . $v['op_key'], (int) $v['supplier_id'], (int) $v['account_id'], $v['amount'], null, $request->user()->id);
            flash(translate('Advance recorded.'))->success();
        } catch (InvalidArgumentException $e) {
            flash($e->getMessage())->error();
        }

        return redirect()->route('purchasing.supplier.payments', ['supplier_id' => $v['supplier_id']]);
    }

    // ── Returns to the supplier ───────────────────────────────────────────

    public function supplierReturns(Request $request)
    {
        if ($r = $this->ready()) {
            return $r;
        }
        $sid = (int) $request->query('supplier_id');
        $rows = DB::table('goods_receipt_lines as l')->join('goods_receipts as g', 'g.id', '=', 'l.goods_receipt_id')->join('products as p', 'p.id', '=', 'l.product_id')->join('suppliers as s', 's.id', '=', 'g.supplier_id')
            ->when($sid, fn ($q) => $q->where('g.supplier_id', $sid))->where('g.status', 'posted')->orderByDesc('l.id')->limit(200)
            ->get(['l.id', 'g.receipt_no', 'g.invoice_status', 's.business_name', 'p.name', 'l.variant', 'l.qty', 'l.unit_cost'])
            ->map(function ($r) {
                $r->returned = (int) DB::table('stock_movements')->where('type', 'supplier_return')->where('source_type', 'goods_receipt_line')->where('source_id', $r->id)->sum(DB::raw('-on_hand_delta'));

                return $r;
            });
        $suppliers = DB::table('suppliers')->orderBy('business_name')->get(['id', 'business_name']);

        return view('backend.purchasing.supplier_returns', compact('rows', 'suppliers', 'sid'));
    }

    public function returnToSupplier(Request $request, \App\Services\Purchasing\SupplierReturnService $svc)
    {
        $v = $request->validate(['op_key' => 'required|string|max:100', 'receipt_line_id' => 'required|integer', 'qty' => 'required|integer|min:1', 'note' => 'nullable|string|max:255']);
        try {
            $r = $svc->returnGoods('sr:' . $v['op_key'], (int) $v['receipt_line_id'], (int) $v['qty'], $request->user()->id, $v['note'] ?? null);
            flash(($r['replayed'] ?? false) ? translate('This return was already recorded.') : translate('Returned to supplier. Supplier credit: ') . $r['supplier_credit'])->success();
        } catch (InvalidArgumentException $e) {
            flash($e->getMessage())->error();
        }

        return back();
    }
}
