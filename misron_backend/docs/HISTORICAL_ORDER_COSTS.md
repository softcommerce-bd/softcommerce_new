# Reporting historical orders and uncosted lines

**Status of the risk: UNRESOLVED until the accountant accepts this policy.** What is implemented is a reporting rule. No cost is invented and no order line, stock row or cost layer is rewritten.

## The four kinds of order line and how each is reported
| Kind | How to recognise it | Reported as | In "FINAL profit"? |
|---|---|---|---|
| Confirmed-order line, shipped with real cost layers | `stock_mode='v2'`, `cost_status='final'` | FINAL (actual FIFO cost recorded at dispatch) | **Yes, only these** |
| Confirmed-order line, shipped with units that had no cost layer | `stock_mode='v2'`, `cost_status='uncosted'` | UNCOSTED, excluded from profit; an optional planning ESTIMATE (product's estimated cost, snapshotted at dispatch) is shown separately and labelled "not final"; it is never booked | No |
| Confirmed-order line not shipped yet | `stock_mode='v2'`, no cost status | awaiting dispatch, excluded | No |
| Historical line (order placed before the upgrade) | `stock_mode='legacy'` | HISTORICAL. If the old system stored a cost (`buying_price > 0`): "cost as recorded by the old system, not verified" with the profit it implies, **reported as its own subtotal**. If it stored none: "cost unknown", no profit. | **Never** |
Cancelled orders are not counted in any total.

## Rules
1. A historical figure is shown exactly as stored. It is not recomputed from today's cost layers, not replaced by an estimate and not added to FINAL profit.
2. The original columns (`buying_price`, `profit`, `fifo_batch_info`, `cost_status`) of historical lines are never written by any report, migration or command. A regression test checks that opening the report changes no order line.
3. An unknown cost stays unknown. The way to give future/remaining stock a cost is the approved `inventory:opening-cost` file; it never touches orders already shipped.
4. Whether recorded historical costs deserve trust, and whether any historical line should be finalized (which would change when COGS is recognised), is an accounting decision (see the difference register D-04, D-05). It is not taken by software.

## Evidence in the staging copy (read-only)
All 34 order lines are `legacy`, none has batch information, 34 carry a recorded `buying_price`, 31 of 34 belong to cancelled orders (not counted). The Order Profit report now shows these lines under HISTORICAL, with FINAL profit 0.00.

## Remaining limitations
* The recorded historical cost cannot be verified against layers (no batch information on those lines).
* Reported profit for historical periods therefore cannot be called final or audited.
