<?php

namespace App\Services\Purchasing;

use App\Services\Inventory\DemandService;
use App\Services\Inventory\StockBalance;
use Illuminate\Support\Facades\DB;

/**
 * "What must I buy?": confirmed, unfulfilled demand that stock and open purchase orders do not cover.
 * Pending and cancelled orders never appear (they hold no demand). Identical product+variation lines of different orders
 * are consolidated; the per-order rows stay visible and linkable.
 */
class PurchaseRequirementsService
{
    /** Units of one demand not yet covered by physical allocation or by an open linked purchase line. */
    public static function uncoveredForDemand(object $d): int
    {
        $outstanding = DemandService::outstanding($d);
        $linkedOpen = (int) DB::table('purchase_requirement_links')->where('order_demand_id', $d->id)->where('status', 'open')->selectRaw('COALESCE(SUM(qty_linked - qty_received),0) q')->value('q');

        return max(0, $outstanding - (int) $d->qty_allocated - $linkedOpen);
    }

    public static function status(object $d): string
    {
        $outstanding = DemandService::outstanding($d);
        if ($d->status === 'cancelled') {
            return 'cancelled';
        }
        if ($outstanding === 0) {
            return 'fulfilled';
        }
        if ((int) $d->qty_allocated >= $outstanding) {
            return 'ready_for_fulfillment';
        }
        $linkedOpen = (int) DB::table('purchase_requirement_links')->where('order_demand_id', $d->id)->where('status', 'open')->sum(DB::raw('qty_linked - qty_received'));
        if ((int) $d->qty_allocated > 0) {
            return 'partially_received';
        }

        return $linkedOpen > 0 ? 'purchase_ordered' : 'needs_purchase';
    }

    /**
     * @param array{product_id?:int, supplier_id?:int, order_id?:int, from?:string, to?:string, only_shortage?:bool} $filters
     * @return array<int, array<string, mixed>> one row per product+variation
     */
    public function items(array $filters = []): array
    {
        $q = DB::table('order_demands as d')->join('products as p', 'p.id', '=', 'd.product_id')->join('orders as o', 'o.id', '=', 'd.order_id')
            ->where('d.status', 'open')->whereRaw('d.qty_confirmed - d.qty_dispatched - d.qty_cancelled > 0')
            ->select('d.*', 'p.name as product_name', 'p.preferred_supplier_id', 'p.estimated_purchase_cost', 'o.code as order_code');
        foreach (['product_id' => 'd.product_id', 'order_id' => 'd.order_id'] as $f => $col) {
            if (!empty($filters[$f])) {
                $q->where($col, $filters[$f]);
            }
        }
        if (!empty($filters['supplier_id'])) {
            $q->where('p.preferred_supplier_id', $filters['supplier_id']);
        }
        if (!empty($filters['from'])) {
            $q->where('d.confirmed_at', '>=', $filters['from'] . ' 00:00:00');
        }
        if (!empty($filters['to'])) {
            $q->where('d.confirmed_at', '<=', $filters['to'] . ' 23:59:59');
        }

        $items = [];
        foreach ($q->orderBy('d.confirmed_at')->orderBy('d.id')->get() as $d) {
            $k = $d->product_id . '|' . $d->variant;
            $items[$k]['rows'][] = ['demand_id' => $d->id, 'order_id' => $d->order_id, 'order_code' => $d->order_code, 'outstanding' => DemandService::outstanding($d), 'allocated' => (int) $d->qty_allocated,
                'uncovered' => self::uncoveredForDemand($d), 'status' => self::status($d)];
            $items[$k] += ['product_id' => $d->product_id, 'variant' => $d->variant, 'product_name' => $d->product_name, 'preferred_supplier_id' => $d->preferred_supplier_id, 'estimated_purchase_cost' => $d->estimated_purchase_cost];
        }
        foreach ($items as $k => &$it) {
            $s = StockBalance::snapshot($it['product_id'], $it['variant']);
            $it['demand'] = $s['demand'];
            $it['on_hand'] = $s['on_hand'];
            $it['allocated'] = $s['allocated'];
            $it['incoming'] = $s['incoming'];
            $it['available'] = $s['available'];
            // Shortage the business still has to buy: demand with no physical units earmarked, minus everything already on order.
            $it['shortage'] = max(0, $s['demand'] - $s['allocated'] - $s['incoming']);
            $it['order_codes'] = array_values(array_unique(array_column($it['rows'], 'order_code')));
        }
        unset($it);
        if (!empty($filters['only_shortage'])) {
            $items = array_filter($items, fn ($i) => $i['shortage'] > 0);
        }

        return array_values($items);
    }
}
