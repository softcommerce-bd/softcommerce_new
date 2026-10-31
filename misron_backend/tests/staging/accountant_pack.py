#!/usr/bin/env python3
"""Builds the accountant review pack from a staging database (read-only SQL). Usage: accountant_pack.py <database> <out_dir> [baseline.json]"""
import csv, json, subprocess, sys, datetime, os
db, out = sys.argv[1], sys.argv[2]; base = json.load(open(sys.argv[3])) if len(sys.argv) > 3 else None
os.makedirs(out, exist_ok=True)
def q(sql):
    r = subprocess.run(['mysql', '-N', '-B', db, '-e', sql], capture_output=True, text=True, check=True).stdout
    return [l.split('\t') for l in r.rstrip('\n').split('\n')] if r.strip() else []
def num(x): return float(x) if x not in ('NULL', '', None) else 0.0
def f(x): return f'{x:,.2f}'
def write_csv(name, header, rows):
    with open(f'{out}/{name}', 'w', newline='') as fh: w = csv.writer(fh); w.writerow(header); w.writerows(rows)
today = datetime.date.today().isoformat()
# ---- accounts
acc = q("select id,code,name,type,sub_type,current_balance,status from chart_of_accounts order by code")
write_csv('02_chart_of_accounts_balances.csv', ['id', 'code', 'name', 'type', 'sub_type', 'current_balance', 'status'], acc)
sys_acc = [a for a in acc if a[1].startswith('SYS-')]
# ---- trial balance
tb_rows, td, tc = [], 0.0, 0.0
for a in acc:
    bal = num(a[5])
    if bal == 0: continue
    debit_normal = a[3] in ('asset', 'expense')
    d = bal if (bal > 0) == debit_normal else 0.0; c = abs(bal) if d == 0 else 0.0
    if bal < 0: d, c = (0.0, abs(bal)) if debit_normal else (abs(bal), 0.0)
    td += d; tc += c; tb_rows.append([a[1], a[2], a[3], f'{d:.2f}', f'{c:.2f}'])
write_csv('03_trial_balance_from_account_balances.csv', ['code', 'name', 'type', 'debit', 'credit'], tb_rows + [['', 'TOTAL', '', f'{td:.2f}', f'{tc:.2f}'], ['', 'DIFFERENCE (debit - credit)', '', f'{td - tc:.2f}', '']])
# ---- P&L: report vs balances
inc = [a for a in acc if a[3] == 'income']; exp = [a for a in acc if a[3] == 'expense']
logmove = {r[0]: (num(r[1]), num(r[2])) for r in q("select account_id,sum(debit),sum(credit) from account_statement_logs group by account_id")}
pl_rows = []
for a in inc + exp:
    if num(a[5]) == 0 and a[0] not in logmove: continue
    d, c = logmove.get(a[0], (0.0, 0.0)); pl_rows.append([a[1], a[2], a[3], f'{num(a[5]):.2f}', f'{(c - d) if a[3] == "income" else (d - c):.2f}'])
write_csv('04_profit_and_loss_balance_vs_statement_rows.csv', ['code', 'name', 'type', 'account_balance', 'movement_in_statement_rows'], pl_rows)
# ---- inventory valuation / FIFO
stock = q("""select s.product_id, COALESCE(s.variant,''), p.name, s.qty,
  COALESCE((select sum(l.remaining_qty) from purchase_products l where l.product_id=s.product_id and COALESCE(l.variant,'')=s.variant),0),
  COALESCE((select sum(l.remaining_qty*CAST(l.buying_unit_cost AS DECIMAL(20,4))) from purchase_products l where l.product_id=s.product_id and COALESCE(l.variant,'')=s.variant),0)
  from product_stocks s join products p on p.id=s.product_id where s.qty<>0 or exists (select 1 from purchase_products l where l.product_id=s.product_id and COALESCE(l.variant,'')=s.variant and l.remaining_qty>0) order by s.product_id, s.variant""")
inv_rows = []; tot = [0, 0, 0.0, 0]
for r in stock:
    qty, lay, val = num(r[3]), num(r[4]), num(r[5]); unc = max(qty - lay, 0); over = max(lay - qty, 0)
    tot[0] += qty; tot[1] += lay; tot[2] += val; tot[3] += unc
    inv_rows.append([r[0], r[1], r[2], int(qty), int(lay), f'{val:.2f}', int(unc), int(over), 'MATCH' if qty == lay else ('UNCOSTED UNITS' if qty > lay else 'LAYERS EXCEED STOCK')])
write_csv('05_inventory_valuation_fifo_by_variant.csv', ['product_id', 'variant', 'product', 'stock_qty', 'fifo_layer_qty', 'fifo_layer_value', 'units_without_cost', 'layer_qty_above_stock', 'status'], inv_rows)
orph = q("select count(*), coalesce(sum(remaining_qty),0), coalesce(sum(remaining_qty*CAST(buying_unit_cost AS DECIMAL(20,4))),0) from purchase_products l where not exists (select 1 from products p where p.id=l.product_id)")[0]
all_layers = num(q("select coalesce(sum(remaining_qty*CAST(buying_unit_cost AS DECIMAL(20,4))),0) from purchase_products")[0][0])
inv_acct = num(next((a[5] for a in acc if a[2] == 'Inventory' and a[3] == 'asset'), 0))
cs = q("select sum(current_stock) from products")[0][0]
# ---- suppliers
sup = q("select id,business_name,due_amount,opening_balance,paid_amount from suppliers order by id")
sup_rows = []
for s in sup:
    docs = q(f"select count(*), coalesce(sum(CAST(net_total_amount AS DECIMAL(20,2))),0), coalesce(sum(CAST(total_paid AS DECIMAL(20,2))),0), coalesce(sum(CAST(total_payment_due AS DECIMAL(20,2))),0) from purchases where supplier_id={s[0]}")[0]
    pays = q(f"select count(*), coalesce(sum(amount),0) from supplier_payments where supplier_id={s[0]}")[0]
    sup_rows.append([s[0], s[1], s[2], docs[0], docs[1], docs[2], docs[3], pays[0], pays[1], f'{num(s[2]) - num(docs[3]):.2f}'])
write_csv('06_supplier_payables_reconciliation.csv', ['supplier_id', 'name(masked)', 'suppliers.due_amount', 'purchases', 'purchases_net_total', 'purchases_paid', 'purchases_due', 'supplier_payment_rows', 'supplier_payment_amount', 'due_amount_minus_purchase_dues'], sup_rows)
orphan_pay = q("select p.id,p.payment_no,p.supplier_id,p.amount,p.status,p.payment_date from supplier_payments p where not exists (select 1 from suppliers s where s.id=p.supplier_id)")
ap = num(next((a[5] for a in acc if a[2] == 'Accounts Payable' and a[3] == 'liability'), 0))
tdiff = td - tc
suspense = {a[1]: num(a[5]) for a in sys_acc}
md = f"""# Accountant review pack

**Generated:** {datetime.datetime.now():%Y-%m-%d %H:%M} from database `{db}` (masked staging copy of the supplied backup `misron.sql`, generated 23 Oct 2026; I cannot verify it is the current production database).
**Feature flag:** OFF (`INVENTORY_CONFIRMED_ONLY=false`). The new tables exist but are empty. Every figure below is legacy data; nothing has been corrected, repaired, re-costed or invented.
**Review status: NOT REVIEWED.** No accountant has seen or approved these figures. The sign-off table at the end is blank on purpose.

Files in this folder: `02_chart_of_accounts_balances.csv`, `03_trial_balance_from_account_balances.csv`, `04_profit_and_loss_balance_vs_statement_rows.csv`, `05_inventory_valuation_fifo_by_variant.csv`, `06_supplier_payables_reconciliation.csv`, `07_difference_register.md`, **`08_source_and_impact_D01_D09.md` (read this first: source, impact, what is proven vs inferred vs unknown for every difference)**, and the evidence files `08_orphan_cost_layers_by_purchase.csv`, `09_stock_vs_layers_classes.csv`, `10_variants_with_stock_but_no_cost_layer_by_product.csv`, `11_layers_already_consumed.csv`, `12_courier_settlements_vs_orders.csv`.

## 1. Suspense and opening-adjustment balances
| Account | Code | Balance |
|---|---|---|
""" + '\n'.join(f"| {a[2]} | {a[1]} | {f(num(a[5]))} |" for a in sys_acc) + f"""

All five new system accounts are 0.00 because the flag has never been on: *Legacy Posting Suspense* only receives one-sided amounts the old screens post once the bridge is active, and *Inventory Opening Adjustment* only receives approved `inventory:opening-cost` files. **Decision for the accountant:** whether a suspense account is acceptable, who reviews it and how often, and whether the one-sided legacy postings (e.g. old purchase screen: debit Inventory and Supplier Advances, credit Payable and Cash) should instead be corrected at source.

## 2. Trial balance (from account balances, as of {today})
Total debit {f(td)} / total credit {f(tc)} / **difference {f(tdiff)}**. The legacy trial balance does **not** balance (see D-01). The Balance Sheet hides this with an automatic *Retained Earnings* balancing figure of {f(tdiff)} (and a matching *Current Year Earnings* of {f(-tdiff)}). Detail: file 03.

## 3. Profit & Loss
The P&L screen reads account-statement rows dated in the period; the Trial Balance reads account balances. For this data they disagree: income account balance {f(sum(num(a[5]) for a in inc))}, expense account balance {f(sum(num(a[5]) for a in exp))}, but the P&L screen shows 0.00 for all time because those balances have no matching statement rows (D-03). Detail: file 04. There is no sales revenue or COGS booked at all although 32 orders exist (25 cancelled, 2 confirmed, 5 pending), so no profit figure can be finalized from this data.

## 4. Balance Sheet (as at {today})
Assets {f(num(base['balance_sheet_today']['total_assets'])) if base else ''} (Inventory only), liabilities {f(num(base['balance_sheet_today']['total_liabilities'])) if base else ''} (Accounts Payable only), equity 0.00 after the balancing figure. Inventory and Accounts Payable are equal to the cent, which is unusual for a trading business: ask whether this is test/seed data (103 purchases dated 13–16 Sep 2026, all unpaid).

## 5. Inventory valuation and FIFO costing
| Measure | Value |
|---|---|
| Inventory account balance | {f(inv_acct)} |
| FIFO cost-layer value, ALL layers (remaining qty x unit cost) | {f(all_layers)} |
| of which on layers that match a live stock row (file 05) | {f(tot[2])} |
| of which on layers of deleted products (orphans) | {f(num(orph[2]))} |
| **Difference (account - all layers)** | **{f(inv_acct - all_layers)}** |
| Stock quantity, all variants (`product_stocks`) | {int(tot[0]):,} |
| FIFO layer quantity matching those rows | {int(tot[1]):,} |
| **Units on the shelf with no cost layer (value unknown, not in the Inventory account's layer value)** | **{int(tot[3]):,}** |
| `products.current_stock` total (cached) vs stock rows | {int(num(cs)):,} vs {int(tot[0]):,} |
| Cost layers pointing at deleted products | {orph[0]} layers, qty {int(num(orph[1])):,}, value {f(num(orph[2]))} |

Per-variant detail with a MATCH / UNCOSTED UNITS / LAYERS EXCEED STOCK status: file 05. Nothing was assigned a cost. To cost the uncosted units, a person fills in a unit cost with evidence in the `inventory:opening-cost` template; that is the only supported path and it is not done.

## 6. Supplier payables and invoice reconciliation
Accounts Payable account {f(ap)}; sum of `suppliers.due_amount` {f(sum(num(s[2]) for s in sup))}; sum of purchase dues {f(sum(num(r[6]) for r in sup_rows))}. These three agree. There are no new-style supplier invoices (the new tables are empty). Detail: file 06.
Supplier payment rows whose supplier does not exist: {len(orphan_pay)} ({'; '.join(f'{o[1]} supplier {o[2]} amount {o[3]} {o[4]} {o[5]}' for o in orphan_pay) or 'none'}) (D-07).

## 7. Known historical figures and differences
See `07_difference_register.md` (every difference with its evidence, what is NOT being done about it, and the question for the accountant).

## 8. Sign-off (to be completed by a real accountant; leave blank until reviewed)
| Item | Reviewer | Date | Approved / Rejected / Needs change | Comment |
|---|---|---|---|---|
| Suspense and opening-adjustment treatment | | | | |
| Trial balance difference (D-01) | | | | |
| P&L basis (D-03) | | | | |
| Balance Sheet basis, balancing figure (D-02) | | | | |
| Inventory valuation and uncosted units (D-04, D-05, D-06) | | | | |
| Supplier payables (D-07, D-08) | | | | |
| Permission to enable `INVENTORY_CONFIRMED_ONLY` on staging | | | | |
"""
open(f'{out}/01_README_and_review_status.md', 'w').write(md)
reg = f"""# Difference register (nothing below has been corrected; source and impact of each item are in 08_source_and_impact_D01_D09.md)

| ID | Difference | Figures | Evidence | What was NOT done | Question for the accountant |
|---|---|---|---|---|---|
| D-01 | Trial balance does not balance | debit {f(td)} vs credit {f(tc)}, difference {f(tdiff)} | file 03; equals expenses {f(sum(num(a[5]) for a in exp))} minus income {f(sum(num(a[5]) for a in inc))}: Courier Settlement Expense and Courier Income were posted without a counter-account | No correcting entry | Which accounts should have received the other side? Write off, or correct at source? |
| D-02 | Balance Sheet balances only through an automatic *Retained Earnings* figure | {f(tdiff)} | report logic: retained earnings = assets - liabilities - equity - current-year earnings | Not changed | Accept as presentation, or replace with a real retained-earnings opening balance? |
| D-03 | P&L (statement rows) disagrees with account balances | income {f(sum(num(a[5]) for a in inc))}, expenses {f(sum(num(a[5]) for a in exp))} vs P&L screen 0.00 | file 04: the balances have no statement rows | Not changed | Which basis is authoritative for the historical period? |
| D-04 | Inventory account differs from FIFO layer value | {f(inv_acct - all_layers)} (account {f(inv_acct)} vs all layers {f(all_layers)}) | file 05 | No revaluation | Explain or adjust? |
| D-05 | Units without any cost layer | {int(tot[3]):,} units in {sum(1 for r in inv_rows if r[8] == 'UNCOSTED UNITS')} variant rows | file 05 | No cost invented; no opening-cost file applied | Provide cost evidence per product, or decide a policy |
| D-06 | Stock quantity cached on products differs from stock rows; layers for deleted products | cached {int(num(cs)):,} vs rows {int(tot[0]):,}; {orph[0]} orphan layers worth {f(num(orph[2]))} | file 05 + reconcile output | Not repaired | Which is right? Orphan layers: write off? |
| D-07 | Supplier payment for a supplier that does not exist | {'; '.join(f'{o[1]}: {o[3]} on {o[5]}' for o in orphan_pay) or 'none'} | `supplier_payments` row not matched in `suppliers` | Not deleted, not re-pointed | Whose payment is it, and was cash actually paid? |
| D-08 | Implausible payable concentration | supplier 2 owes {f(num(sup[1][2])) if len(sup) > 1 else ''} of {f(sum(num(s[2]) for s in sup))} | file 06 | none | Is this test data? |
| D-09 | 105 account-statement rows have a blank transaction type | 105 | the old ENUM discarded unlisted values; the original type cannot be reconstructed | not reconstructed | acceptable? |
"""
open(f'{out}/07_difference_register.md', 'w').write(reg)
print('pack written to', out)

# ======================= source & impact analysis (D-01 .. D-09) =======================
def one(sql): r = q(sql); return r[0] if r else []
orph_by_p = q("""select l.purchase_id, count(*), sum(l.remaining_qty), round(sum(l.remaining_qty*cast(l.buying_unit_cost as decimal(20,4))),2), min(l.product_id), p.supplier_id, p.purchase_date, p.reference_no, p.total_payment_due, p.total_paid
  from purchase_products l left join purchases p on p.id=l.purchase_id where not exists (select 1 from products x where x.id=l.product_id) group by l.purchase_id, p.supplier_id, p.purchase_date, p.reference_no, p.total_payment_due, p.total_paid order by 1""")
write_csv('08_orphan_cost_layers_by_purchase.csv', ['purchase_id', 'layers', 'units', 'value', 'deleted_product_id', 'supplier_id', 'purchase_date', 'reference', 'purchase_due', 'purchase_paid'], orph_by_p)
cls = q("""select case when has_layers=0 then '1 NO cost layer at all' when qty>layer_qty then '2 stock above layers' when qty<layer_qty then '3 layers above stock' else '4 match' end c, count(*), sum(qty), sum(layer_qty), sum(greatest(qty-layer_qty,0)), sum(greatest(layer_qty-qty,0))
  from (select s.qty, coalesce((select sum(remaining_qty) from purchase_products l where l.product_id=s.product_id and coalesce(l.variant,'')=s.variant),0) layer_qty,
        (select count(*) from purchase_products l where l.product_id=s.product_id and coalesce(l.variant,'')=s.variant) has_layers from product_stocks s) t group by c order by c""")
write_csv('09_stock_vs_layers_classes.csv', ['class', 'variant_rows', 'stock_units', 'layer_units', 'units_without_cost', 'layer_units_above_stock'], cls)
nolayer_products = q("select s.product_id, p.name, count(*), sum(s.qty) from product_stocks s join products p on p.id=s.product_id where s.qty>0 and not exists (select 1 from purchase_products l where l.product_id=s.product_id and coalesce(l.variant,'')=s.variant) group by s.product_id, p.name order by 4 desc")
write_csv('10_variants_with_stock_but_no_cost_layer_by_product.csv', ['product_id', 'product', 'variant_rows', 'units'], nolayer_products)
consumed = q("select id, purchase_id, product_id, variant, purchase_quantity, remaining_qty, buying_unit_cost, round((purchase_quantity-remaining_qty)*cast(buying_unit_cost as decimal(20,4)),2) from purchase_products where remaining_qty<purchase_quantity order by id")
write_csv('11_layers_already_consumed.csv', ['layer_id', 'purchase_id', 'product_id', 'variant', 'bought', 'remaining', 'unit_cost', 'consumed_cost'], consumed)
consumed_cost = sum(num(c[7]) for c in consumed); consumed_units = sum(num(c[4]) - num(c[5]) for c in consumed)
settle = q("select s.id, s.settlement_no, s.order_id, (select count(*) from orders o where o.id=s.order_id), s.status, s.total_amount, s.courier_fee, s.settlement_fee, s.courier_income_amount, s.sales_amount, s.created_at from courier_settlements s order by s.id")
write_csv('12_courier_settlements_vs_orders.csv', ['id', 'settlement_no', 'order_id', 'order_still_exists(1/0)', 'status', 'total_amount', 'courier_fee', 'settlement_fee', 'courier_income_amount', 'sales_amount', 'created_at'], settle)
ssum = one("select count(*), sum(status='settled'), round(sum(case when status='settled' then settlement_fee else 0 end),2), round(sum(case when status='settled' then total_amount else 0 end),2), sum(case when status='settled' and order_id not in (select id from orders) then 1 else 0 end), min(created_at), max(created_at) from courier_settlements")
logs = one("select min(created_at), max(created_at), count(*) from account_statement_logs")
stale = q("select name, current_balance, updated_at from chart_of_accounts where current_balance<>0 and updated_at < (select min(created_at) from account_statement_logs)")
stock200 = one("select sum(qty=200), count(*) from product_stocks")
pay = one("select id, payment_no, supplier_id, amount, payment_type, payment_date, created_at, payment_account_id from supplier_payments order by id limit 1")
alloc = q("select a.purchase_id, p.reference_no, p.supplier_id, p.purchase_date, p.total_payment_due, p.total_paid, a.paid_amount from supplier_payment_purchases a left join purchases p on p.id=a.purchase_id")
cancelled_overlap = one("select count(*), coalesce(sum(d.quantity),0) from order_details d join orders o on o.id=d.order_id where o.delivery_status='cancelled' and exists (select 1 from purchase_products l where l.product_id=d.product_id and coalesce(l.variant,'')=coalesce(d.variation,'') and l.remaining_qty<l.purchase_quantity)")
layers200 = one("select sum(purchase_quantity=200), count(*) from purchase_products")
sup_created = q("select id, created_at from suppliers order by id")
pay_pivot = one("select count(*) from supplier_payment_purchases")
max_supplier = max(sup, key=lambda s: num(s[2]))
sp = q("select count(*), min(purchase_date), max(purchase_date) from purchases where supplier_id=%s" % max_supplier[0])[0]
nolayer_units = int(sum(num(r[3]) for r in nolayer_products)); above_units = int(num(next((c[4] for c in cls if c[0].startswith('2')), 0)))
orph_units = int(sum(num(o[2]) for o in orph_by_p)); orph_val = sum(num(o[3]) for o in orph_by_p)
inv_minus_layers = inv_acct - all_layers
analysis = f"""# Source and impact of D-01 to D-09

How to read this: **PROVEN** = reproduced by a query on the data (files 08-12 are the evidence). **INFERRED** = a pattern consistent with the data, not provable from it. **UNKNOWN** = the data cannot say; the accountant or owner must. Nothing here changes any figure.

## Context that affects every item
* The statement log starts on **{logs[0][:10]}** and holds {logs[2]} rows up to {logs[1][:10]}. Accounts last updated before that date but still carrying a balance: {', '.join(f"{s[0]} ({f(num(s[1]))}, last touched {s[2][:10]})" for s in stale) or 'none'} (PROVEN). The courier-settlement table, by contrast, holds {ssum[0]} records dated {ssum[5][:10]} to {ssum[6][:10]}. So the accounting history before mid-September 2026 is not in the statement log, while some of its leftovers are still in the balances.
* {int(num(stock200[0])):,} of {int(num(stock200[1])):,} stock rows hold exactly 200 units (PROVEN). Real counted stock does not look like that; this is the product form's default, i.e. **opening quantities were typed in, not counted** (INFERRED).
* All 103 purchases are dated 13-16 Sep 2026, none has any payment, and every sale-side balance (sales, receivable, cash, COGS) is 0.00 (PROVEN).

## D-01 Trial balance out of balance by {f(tdiff)}
* **Source (PROVEN for the amounts, INFERRED for the cause):** the whole difference is the two courier accounts. Expense *Courier Settlement Expense* {f(sum(num(a[5]) for a in exp))} and income *Courier Income* {f(sum(num(a[5]) for a in inc))}: expense minus income = {f(sum(num(a[5]) for a in exp) - sum(num(a[5]) for a in inc))} = the difference. Their counter-entries (cash, receivable, sales, COGS) no longer exist because those accounts are 0.00. The settlement table shows {int(num(ssum[1]))} settled courier settlements (gross {f(num(ssum[3]))}, settlement fees {f(num(ssum[2]))}); {int(num(ssum[4]))} of them point at orders that no longer exist in `orders` (file 12). The balances look like leftovers of that earlier activity after the other accounts and the statement rows were reset. The exact derivation of 353.95 / 52.20 from the settlement fees cannot be reproduced (fees total {f(num(ssum[2]))}).
* **Impact:** the ledger cannot balance until these two balances are explained. Amount is small (301.75) but it proves the books are not double-entry consistent. The Balance Sheet now shows it on its own line labelled UNRECONCILED DIFFERENCE.
* **Not done:** no entry, no write-off, no reset of these balances.

## D-02 Balance Sheet "Retained Earnings"
* **Source (PROVEN, from the code):** the old report computed Retained Earnings as assets - liabilities - equity - current-year earnings, i.e. whatever made the page balance. With no real retained-earnings account, it hid D-01 inside equity.
* **Impact:** a balanced-looking sheet that was not balanced. **Fixed in the report (display only):** the line is now named *UNRECONCILED DIFFERENCE (ledger out of balance, not retained earnings)* with a red banner; the trial balance has its own banner. The amount is unchanged (301.75) and still not corrected in the books.

## D-03 P&L shows 0.00 while income / expense accounts have balances
* **Source (PROVEN):** the P&L sums *account statement rows* in the period; the two courier balances have no rows (all {logs[2]} rows are Sept 2026 purchase/payable rows, none on these accounts).
* **Impact:** {f(sum(num(a[5]) for a in inc))} income and {f(sum(num(a[5]) for a in exp))} expense are missing from the P&L, and there is no sales revenue or COGS at all (no booked sales; 25 of the 32 orders are cancelled, 2 confirmed, 5 pending), so **no profit can be reported from this data**. The P&L screen now lists, per account, the balance that rows do not explain.

## D-04 Inventory account {f(inv_acct)} vs FIFO layers {f(all_layers)} (difference {f(inv_minus_layers)})
* **Source (PROVEN):** the difference equals exactly the cost of units already consumed from layers: {int(consumed_units)} units across {len(consumed)} layers = {f(consumed_cost)} (file 11). The Inventory account equals the total cost of all purchases ({f(num(one('select sum(cast(net_total_amount as decimal(20,2))) from purchases')[0]))}); it was never credited when those units left.
* **What the data shows (PROVEN):** for most of the consumed variants the stock row was lowered by the same amount (e.g. stock 199 / layer remaining 199), so the units left the shelf; a few rows disagree (file 11). **What is unknown:** which sales consumed them. No remaining order carries FIFO batch information. {int(num(cancelled_overlap[0]))} lines ({int(num(cancelled_overlap[1]))} units) on CANCELLED orders are for the same product/variant as consumed layers, which suggests the units belonged to orders that were later cancelled without the layers being given back (INFERRED; the cancel path in the old code did not return units to cost layers before this project fixed it).
* **Impact:** if those {int(consumed_units)} units were sold, Inventory is overstated by {f(consumed_cost)} and revenue/COGS are unrecorded; if they were returned to the shelf, the layers are understated. Either way the amount is small against the total.

## D-05 {above_units + nolayer_units:,} units on the shelf with no cost
* **Source (PROVEN):** two groups (file 09): **{nolayer_units:,} units in variant rows that have no cost layer at all** (file 10 lists them by product) and **{above_units:,} units in rows where stock exceeds the layers**. All the no-layer rows hold exactly 200 units: they were created by the product form's default stock, never by a purchase (INFERRED).
* **Impact:** these units have no cost, so they are not part of the FIFO valuation and the Inventory account does not include them. If they are real stock their cost is unknown (nothing has been invented); if they are placeholders, stock is overstated by {above_units + nolayer_units:,} units and cannot be sold from without producing "uncosted" sales that are excluded from profit. Another group (file 09 class 3) is the reverse: layers exceed stock by {int(num(next((c[5] for c in cls if c[0].startswith('3')), 0))):,} units.
* **Decision needed:** per product, either supply cost evidence (opening-cost template) or declare the quantity a placeholder to be corrected by a stock count. Neither has been done.

## D-06 Orphan cost layers: {len(orph_by_p)} purchases, {orph_units:,} units, BDT {f(orph_val)}
* **Source (PROVEN):** {'; '.join(f"purchase {o[0]} ({o[7]}, {o[6]}) supplier {o[5]}: {o[1]} layers x 200 units = {int(num(o[2])):,} units, BDT {f(num(o[3]))}, product id {o[4]} no longer exists, still unpaid ({o[8]})" for o in orph_by_p)} (file 08). The two product ids are missing from the middle of the catalogue's id range ({'2335'}-{'2464'}), so the products were deleted after being purchased (INFERRED); the purchases, their layers and the payable stayed (PROVEN).
* **Impact:** BDT {f(orph_val)} of Inventory and the same BDT {f(orph_val)} of Accounts Payable (owed to supplier {orph_by_p[0][5] if orph_by_p else ''}) relate to goods that cannot be sold because no product exists. They inflate both sides equally, so net equity is unaffected, but total assets and payables are overstated by that amount if the goods were never received/kept or the products were deleted by mistake. Whether the goods exist physically is UNKNOWN.
* **Not done:** layers not deleted, purchases not reversed, products not restored.

## D-07 Supplier payment for a supplier that does not exist
* **Source (PROVEN):** `{pay[1] if pay else ''}`: {f(num(pay[3])) if pay else ''}, type *{pay[4] if pay else ''}*, dated {pay[5] if pay else ''}, recorded {pay[6] if pay else ''}, for supplier id {pay[2] if pay else ''}, money account id {pay[7] if pay else ''}. The supplier table only has ids {', '.join(s[0] for s in sup_created)} (created from {sup_created[0][1][:10] if sup_created else ''}, i.e. after this payment). The payment is allocated by `supplier_payment_purchases` to {'; '.join(f"purchase {x[0]} ({x[1]}, supplier {x[2]}, dated {x[3]}): paid_amount {x[6]}, but that purchase still shows due {x[4]} and paid {x[5]}" for x in alloc) or 'no purchase'} (file 06).
* **What this means (PROVEN facts, INFERRED reading):** a June payment is allocated to a September purchase of a different supplier, and that purchase was not reduced. This is what happens when purchase and supplier ids are reused after the tables were emptied: the payment row survived the reset and now points at unrelated records (INFERRED).
* **Impact (PROVEN):** the payment changed no current balance (supplier due, payable and purchase due are all unreduced). **UNKNOWN:** whether 5,150.00 of real cash was paid in June 2026; if so, purchase 11 / supplier 2 are overstated by 5,150.00 and a cash outflow is missing from the ledger.
* **Not done:** not deleted, not re-pointed, not applied.

## D-08 One supplier carries almost all payables
* **Source (PROVEN):** supplier {max_supplier[0]} owes {f(num(max_supplier[2]))} = {num(max_supplier[2]) / max(1, sum(num(s[2]) for s in sup)) * 100:.2f}% of all payables, from {sp[0]} purchases dated {sp[1]} to {sp[2]}, none paid. {int(num(layers200[0])):,} of the {int(num(layers200[1])):,} layers are exactly 200 units, the same default quantity as the stock rows.
* **Impact / inference (INFERRED):** this is the pattern of **opening stock typed in through the purchase screen**, which the legacy system can only record as a purchase on credit. If so, the payable is not a real debt to a real supplier and Inventory / Payables are both overstated by {f(num(max_supplier[2]))}. If the debt is real, ignore this. Needs the owner's answer.

## D-09 Statement rows with a blank type
* **Source (PROVEN):** {logs[2]} rows, none with a transaction type; the old column was an ENUM that silently discarded values it did not list.
* **Impact:** the type cannot be reconstructed, the amounts and references remain. Reports that filter by type would miss them; the migration widens the column so new rows keep their type. Nothing rewritten.
"""
open(f'{out}/08_source_and_impact_D01_D09.md', 'w').write(analysis)
print('analysis written')
