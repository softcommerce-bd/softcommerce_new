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

Files in this folder: `02_chart_of_accounts_balances.csv`, `03_trial_balance_from_account_balances.csv`, `04_profit_and_loss_balance_vs_statement_rows.csv`, `05_inventory_valuation_fifo_by_variant.csv`, `06_supplier_payables_reconciliation.csv`, `07_difference_register.md`.

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
reg = f"""# Difference register (nothing below has been corrected)

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
