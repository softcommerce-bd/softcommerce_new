# Inventory, Purchasing & Accounting upgrade

Status: implemented on a disposable MariaDB 10.11 test database; **nothing has been run against production**. `INVENTORY_CONFIRMED_ONLY` ships **off**: with it off, order handling is the same as before the upgrade.

## 1. Model (one source of truth per quantity)
| Quantity | Where it lives |
|---|---|
| Physical on-hand | `product_stocks.qty` (existing column, now strictly physical, never negative through the new code) |
| Confirmed outstanding demand | `order_demands` (one row per order line): outstanding = confirmed − dispatched − cancelled; counter `stock_balances.demand` |
| Allocated | `order_demands.qty_allocated` (a **subset** of demand); counter `stock_balances.allocated` |
| Incoming | open purchase orders: Σ `purchase_order_details.pending_quantity` where PO status is `ordered`/`partially_received` |
| Damaged (returned, not sellable) | `stock_balances.damaged` |
| Cost layers (FIFO) | `purchase_products.remaining_qty` (existing) |
| Audit ledger | `stock_movements`, `stock_allocations` |

`Available to sell = on-hand − outstanding demand` (allocated units are already inside demand: never subtracted twice). Allocation is oldest-confirmation-first and re-runs after every receipt, release and return.

Order-line state machine (`OrderStockTransitions`, run by `OrderStockObserver` on every save that changes `orders.delivery_status`, from any controller/command):
`pending` nothing · `confirmed/processing/courier_assigned/steadfast/pathao` demand created once · `picked_up/on_the_way/shipped/delivered` dispatch (physical −, demand −, allocation −, FIFO cost consumed once, cost written to the line) · `cancelled` release only what has not shipped. A dispatch with fewer allocated units than needed is **refused and the status is not saved**.
Lines created before the switch keep `stock_mode='legacy'` (stock already deducted at creation; old cancel/restock rules); new lines are `v2`. POS sells goods on the spot: it passes `['immediate' => true]` to `StockLedger::take`, stays legacy, and cannot take units earmarked for confirmed orders.

## 2. Workflows (all covered by the tests listed in §6)
Product: policy (`stock_only` / `backorder` / `preorder`, max qty, preorder date, preferred supplier, estimated cost, lead time) in Purchasing & Stock → Stock Overview → Settings. · Pending order: policy check only, nothing held. · Confirmed: demand +n, available may go negative, physical unchanged, no FIFO touched. · Purchase requirement: requirements screen = demand − allocated − incoming; creating a PO links lines to demands and refuses covered demand. · Supplier purchase: PO draft/ordered (no stock, no books). · Goods receiving: partial allowed; stock +, FIFO layer at landed cost, `Dr Inventory / Cr GRNI` (+ `Cr` the account that bore freight), waiting orders allocated. · Invoice: `Dr GRNI (+/- PPV) / Cr Accounts Payable`; supplier due +. · Payment: `Dr AP / Cr cash|bank|mobile`; advances `Dr Supplier Advances`; never an expense. · Dispatch: see §1. · Cancel before dispatch: released once, freed units go to the next order. · Cancel after dispatch: stock untouched. · Return: *Receive customer returns* screen: resellable → stock + original FIFO layer; damaged/rejected → recorded only. · Profit: `order_details.profit = price − FIFO cost`, only when every unit had a real cost layer (`cost_status='final'`).

## 3. Schema (migrations in `database/migrations/inventory_v2`, SQL mirror in `sqlupdates/inventory_v2.sql`, rollback `inventory_v2_rollback.sql`)
New: `idempotency_keys`, `stock_balances`, `order_demands`, `stock_movements`, `stock_allocations`, `goods_receipts`, `goods_receipt_lines`, `supplier_invoices`, `supplier_invoice_payments`, `purchase_requirement_links`, `stock_return_receipts`, `journal_entries`, `journal_lines`. Columns: `products` (policy fields), `order_details` (`stock_mode`, `cost_status`, `provisional_cost` [unused, reserved]), `purchase_order_details.variant`. Widened: `account_statement_logs.transaction_type` ENUM→VARCHAR(50), `purchase_orders.status`→VARCHAR(20). Permissions: 24 in section `purchasing_inventory`; **every existing role is granted them once** (today's effective access) so nobody is locked out; tighten in Staff → Roles. Three clearing accounts are inserted only if missing (GRNI, Purchase Price Variance, Supplier Advances).
`sqlupdates/inventory_v2_innodb_conversion.sql` converts `purchases, purchase_products, purchase_returns, purchase_return_products, suppliers` to InnoDB and is a **prerequisite**: the switch refuses to turn on while they are MyISAM (`php artisan inventory:preflight`).
Tested: both migration routes on a copy of `misron.sql`: identical schema (154 columns compared), all row counts and balance totals identical before/after, rollback leaves legacy data intact. **Not run on production.**

## 4. Security remediation
`routes/admin.php`: the updater, supplier, purchase, purchase-order, draft, courier-settlement, accordion, order-settlement and four `/dashboard/*` data routes were registered with no login check; now inside `['auth','admin','prevent-back-history']` (URLs unchanged). Per-action Spatie permissions added to the Supplier, Purchase, PurchaseOrder, SupplierPayment, Draft, CourierSettlement, StockAdjustment controllers. State-changing GETs removed: `purchase/delete`, `supplier/delete`, `draft/delete`, `purchase-order/delete`, `order/edit/delete` (views updated; now DELETE/POST + CSRF). A route that pointed at a non-existent controller (broke `route:list`/`route:cache`) is guarded.
Still open: bulk order status change from the orders list is a GET form (`all_orders?bulk_delivery_status=`), and `purchase_history/destroy/{id}` (customer cancel) is a GET.

## 5. Accounting layer
`JournalService`: ≥2 lines, one positive side per line, debits = credits (cents), idempotency key, one-time reversal, accounts locked in id order, `chart_of_accounts.current_balance` updated in the same transaction, legacy `account_statement_logs` rows written. `Money` is integer-based (4 dp); no floats. **Cut-over is not done**: existing screens/reports (P&L, Balance Sheet, legacy purchase/supplier-payment/POS postings) still use the old single-sided balances; the journal covers only what the new services post. Trial Balance and General Ledger (journal) are new; `inventory:reconcile` compares journal, supplier dues, AP and FIFO valuation.

## 6. Tests (MariaDB 10.11 test DB)
Full PHPUnit: **458 tests**; the only failures are the 7 that also fail on the pre-upgrade commit (MariaDB lacks `transaction_isolation`, a funnel FK created only by the artisan route, a missing uploads dir, …). Concurrency: 30 tests, 1 pre-existing legacy deadlock failure; the 8 new ones pass repeatedly. New: ConfirmedOrderStockTest 18, PurchasingAccountingTest 15, OrderChannelsV2Test 7, PurchasingScreensTest 16, SettlementCostV2Test 2, FinancialRoutesSecurityTest 9, InventoryV2ConcurrencyTest 8 (real parallel processes; they found and I fixed a lock-upgrade deadlock, a snapshot-read under-allocation, and a first-use account-creation race).

## 7. Production deployment
1. Maintenance window; **full backup** (`mysqldump --single-transaction`), restore it on a copy and rehearse everything below there first, including `php artisan inventory:reconcile` before and after.
2. Deploy code; `composer install --no-dev`; frontend build as usual. Keep `INVENTORY_CONFIRMED_ONLY=false`.
3. Run `sqlupdates/inventory_v2_innodb_conversion.sql` (or the artisan-free equivalent) on the live DB; confirm the five tables are InnoDB. (The legacy `inventory_v1_stock_safety.sql` unique key on `product_stocks` is recommended too.)
4. `php artisan migrate --path=database/migrations/inventory_v2 --force` **or** import `sqlupdates/inventory_v2.sql` once. Clear caches (`php artisan optimize:clear`, admin "Clear Cache").
5. Staff → Roles: review the new "Purchasing Inventory" permissions per role; log in as a non-admin staff user and as a logged-out visitor and confirm access.
6. Queue/scheduler unchanged (cron `schedule:run`).
7. Review `php artisan inventory:reconcile`: decide the data-repair items (§8) **before** switching on.
8. Switch on: set `INVENTORY_CONFIRMED_ONLY=true`, `php artisan config:clear`, `php artisan inventory:preflight` must say Ready. Orders open at that moment stay legacy; new ones are v2. Set the policy of products you want to sell at zero stock to Backorder.
9. Post-checks: place a pending order (stock unchanged), confirm it (Stock Overview shows demand, available may be negative), receive goods, ship it; `inventory:reconcile` shows `v2_invariants` OK and journal balanced.
Rollback: set `INVENTORY_CONFIRMED_ONLY=false` (v2 lines keep working until shipped/cancelled; nothing breaks). Full rollback only when no line is `v2`: `sqlupdates/inventory_v2_rollback.sql`, else restore the backup.

## 8. Existing data: findings and repair plan (nothing is changed automatically)
From the repository dump (`misron.sql`; production counts may differ): 606 stock rows ≠ their cost layers, 416 positive-stock rows have no cost layer, 68 products' `current_stock` ≠ their rows, 80 cost layers reference deleted products, 105 blank transaction types, Inventory account vs FIFO valuation differ by 11,255.00. Plan: (a) `current_stock` is derived: recompute from rows once approved; (b) stock vs layers needs a physical count by item; (c) stock without cost needs an **approved opening cost** (a one-off goods-receipt "opening balance" posting) — until then dispatching those units marks the line `uncosted`, excluded from profit and settlement COGS and flagged on the dashboard; (d) orphaned layers: investigate, never delete; (e) blank types: leave; (f) re-run reconcile after (a)–(c) and let the accountant explain the remainder.

## 9. Integration and stabilisation changes (second phase)
* **Permissions are secure by default.** The 24 purchasing permissions are created but granted to no role. Review and grant with `php artisan inventory:grant-permissions` (profiles: purchasing_clerk, warehouse, inventory_viewer, finance, manager, preserve_previous_access; `--dry-run` first; additive only).
* **State-changing GETs removed:** bulk order status (`POST admin/bulk-order-status`), customer order cancel (`POST purchase_history/destroy/{id}`; API gets `POST api/v2/order/cancel/{id}`, the old API GET is kept, deprecated, for the mobile app).
* **Allocation guard:** legacy purchase edit/adjust/return can no longer take units earmarked for confirmed orders.
* **Legacy purchase screen:** keeps working (single legacy posting; nothing is posted twice). It gets a duplicate-submit key, and purchases created by Goods Receiving (or opening-cost layers) cannot be edited, deleted or returned through it (use Purchasing screens / supplier returns).
* **Supplier returns** for goods received through the new flow: `SupplierReturnService` (journalled; GRNI, payable credit note or supplier advance; refuses sold or allocated units).
* **Invoice price differences:** the unsold share is revalued into the FIFO layer and Inventory; the sold share goes to Purchase Price Variance; tax/freight go to PPV. Historical `order_details.buying_price` is never rewritten.
* **Opening cost for legacy stock without cost:** `inventory:opening-cost list|template|check|apply`. Never invents a cost; only rows with an explicit unit_cost are used; dry run unless `--commit --approved-by=NAME`; no stock change; Dr Inventory / Cr Inventory Opening Adjustment; idempotent by file hash. Dispatched lines already `uncosted` stay excluded from profit.
* **Accounting reconciliation** is part of `inventory:reconcile` (journal vs statement mirror, payable vs open invoices, GRNI vs uninvoiced receipts, payments without journal, trial balance, profit exclusions).

## 10. Not delivered / limits (be aware)
* POS, courier settlement, legacy supplier payment and the legacy Balance Sheet/P&L still use the legacy accounting only (they are not journalled, which avoids double posting). The reconciliation shows legacy vs journal differences; mixing old and new documents for one supplier is not recommended.
* Provisional cost is not implemented; units without a cost layer are `uncosted` and excluded from profit.
* The legacy Balance Sheet still ignores its date filter.
* Browser testing was run in a sandbox without the admin theme CSS/JS assets (404) or CDN access: screens render without server errors and the key flows work, but visual/mobile layout, jQuery-dependent widgets and charts could not be verified. Do this on staging.
* The repository has no CORS middleware (the storefront relies on web-server CORS in production); the storefront browser test supplied the headers itself.
* Full storefront checkout in a browser (address form to confirmation page) was not completed; the order endpoint is tested at HTTP level.
* The v2 model has no multi-warehouse support.
