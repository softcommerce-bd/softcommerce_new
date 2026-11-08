# The two deferred end-to-end failures: investigation (commit `98339a1` + handover)

Question: can they be resolved without the real (proprietary, not-in-repository) admin theme? **Answer: one was a stand-in artifact and passes with a test-only Bootstrap stand-in; the other is genuinely theme-dependent and stays DEFERRED. Neither is hidden or downgraded.** Neither concerns money or stock. Environment: local Laravel (`tests/e2e/router.php`) on the disposable database `misron_e2e_test`, built Next.js storefront, Chromium (Chrome-channel symlink), MariaDB 10.11.14.

## 1. What was found (evidence `docs/evidence/final10/`)
| Spec | Symptom without a theme | Cause | With the stand-in (`tests/e2e/build-standin-theme.sh`) | Disposition |
|---|---|---|---|---|
| `ui-review.spec.js:90` analytics dashboard on a phone | 169 px sideways overflow in the full run; passes when run alone | pure framework artifact: Bootstrap `.row` has -15 px side margins; the real theme pads `.aiz-main-content`, an unthemed page does not. Data-dependent (wider tables in a full run) | **PASSES** after the stand-in pads `.aiz-main-content` (full run `e2e_full_with_standin_theme_v3.txt`: analytics passes; earlier runs with the first stand-in showed 15 px = the missing padding) | **RESOLVED** (test artifact). No application change. |
| `ui-review.spec.js:110` save a funnel as a template; library pages fit a phone | step 1: the "Save template" button is never found (4 min timeout): the modal keeps `aria-hidden="true"` because Bootstrap modal JS is absent. Step 2 (with Bootstrap + jQuery): the flash message "Saved as a template" is not shown: the layout calls the theme's `AIZ.plugins.notify`. Step 3 (stand-in with a notify shim): the spec reaches the phone-width funnels list and fails `noSideways` by 280 px | the funnels list uses the theme's `aiz-table` class, whose responsive behaviour (and the off-canvas sidebar on phones) comes from the theme's own CSS/JS; without it the table and the fully expanded sidebar overflow a 390 px screen | still FAILS at the phone-width funnels list (`e2e_ui_review_with_standin_v3.txt`, `e2e_full_with_standin_theme_v3.txt`) | **DEFERRED to the real theme (E7).** Wrapping the table in `.table-responsive` would likely silence it, but it could not be verified against the real DataTables plugin, so no production template was changed. |
## 2. Run summaries
| Run | Passed | Failed |
|---|---|---|
| no theme, first run (database missing `sales_funnel` v4/v5 SQL: environment) | 31 | 28 (+23 not run) |
| no theme, after applying v4/v5 SQL | 79 | 3 (templates spec flaky; analytics; template save) |
| stand-in v2 (Bootstrap + padding) | 80 | 2 (analytics 15 px - fixed in v3; template save) |
| stand-in v3 (+ `AIZ.plugins.notify` shim) | 80 | 2: template save (phone list, theme-dependent) and **one intermittent storefront guest-checkout spec** (order count 0 after a 30 s poll in a loaded full run) |
**Intermittent failure, not explained away:** `storefront-checkout.spec.js` "a guest buys ... cash on delivery" passed in the two earlier full runs and **4 of 4 times when repeated alone** (`e2e_storefront_checkout_repeat4.txt`), but failed once in the v3 full run (the order had not arrived within 30 s while the machine was busy). It is a checkout path, so it is recorded here as an open observation (timing under load; not reproduced; no order was duplicated or lost in the passing runs). It should be re-run on the staging engineer's environment.

## 3. What changed in the repository for this
`tests/e2e/router.php` (optional `E2E_THEME_DIR`: serves stand-in theme files; test-only), `tests/e2e/build-standin-theme.sh` (builds the stand-in from npm Bootstrap 4.6 + jQuery 3.6; padding rule; `AIZ.plugins.notify` shim). No application, view, CSS or JS file of the product was changed. Setup note: the e2e database must also have `sqlupdates/sales_funnel_v4_publishing.sql` and `sales_funnel_v5_analytics.sql` applied (the documented `migrate` step fails on a base database that already has the order columns).

## 4. Reproduce
```bash
tests/e2e/build-standin-theme.sh /tmp/standin
E2E_THEME_DIR=/tmp/standin npx playwright test        # from misron_frontend
```
Expected with the real theme (E7): re-run the whole suite; if `ui-review.spec.js:110` still fails there, it is a product defect to fix.
