# Decision request to the owner (T-5)

Commit `98339a1`. T-5 is technically complete; the following need **you**. Each unanswered item keeps today's safe behaviour. Recommendations are advice, not approvals. Tracker rows: E6, E7, E9, E11.

| ID | Question | Recommended | If undecided | Your answer |
|---|---|---|---|---|
| E9 | Provide an **isolated staging environment** (sandbox-only gateway/courier/SMS credentials, one scheduler host, cannot reach production)? | yes | nothing can be deployed or smoke-tested | |
| E7 | Provide the **admin theme package** (css/js) so UI checks can run on the real look | yes | two funnel-admin UI specs stay deferred; P4 blocked | |
| DEC-OPS | Confirm `FINANCIAL_ENGINE_POLICY=enforce` (purchasing/supplier-balance changes are **refused** while their tables are not InnoDB) or choose `warn`/`off` in writing | enforce | enforce stays (default) | |
| R01-A | Approve the rehearsal of the MyISAM -> InnoDB conversion on a masked restore (DBA) | yes | no conversion window can be planned | |
| DEC-16 | Run the nightly auto-deposit at all? a) with a non-login system user (plus the ENUM schema step and an accounting rule for charges) b) other c) stay paused | c | stays paused (safe) | |
| DEC-07 | Create a `document_sequences` table (schema change) for the other numbered series, or accept the new lock-based protection only | accept the lock-based protection for now; decide the table later | locks stay (tested) | |
| DEC-01 | Wording and status for "already done" answers on repeated requests; which forms carry tokens | token + "already done" | repeat answers stay "not found" | |
| DEC-05 | Checkout duplicate identity: token mandatory/optional, retention, owner of the payment view | optional token + cart-identity fallback | cart identity stays | |
| DEC-09 | Purchase-order conversion design (`convertToPurchase` is not transactional) | approve a design | stays as is (known gap) | |
| DEC-11 | Retention of idempotency keys; alert owner | 30 days token / 24 h fallback | keys grow | |
| DEC-12 | Data-access approval for the full Phase 0 audit SQL on an isolated restore | yes, read-only, restore only | duplicates/orphans unknown; blocks DEC-14 | |
| DEC-15 | Order cancel/delete/status without transaction; notification outbox pattern | approve the pattern | those paths stay unprotected | |
| DEC-18/19 | N-1.2 correction procedure and approver; N-1.3 edit audit table | accountant-led; table later | no correction; no audit table | |
| DEC-20 / E11 | **P6**: written approval to enable `INVENTORY_CONFIRMED_ONLY` on staging; later, final acceptance | not before E1-E10 are satisfied | flag stays false | |
Also: confirm nobody sets `AUTO_DEPOSIT_APPROVED` or `AUTO_DEPOSIT_USER_ID` anywhere, and that the scheduler host is never rolled back to a release older than commit `25aec82`.
Signed by / date: __________
