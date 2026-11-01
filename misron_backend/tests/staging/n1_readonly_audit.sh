#!/usr/bin/env bash
# N-1 (decision N-1.1): READ-ONLY supplier-payment investigation Q1-Q10, for an AUTHORISED accountant/DBA, on an ISOLATED RESTORED COPY ONLY.
#
#   N1_CONFIRM_ISOLATED_RESTORE=yes tests/staging/n1_readonly_audit.sh <database> <output-dir> [mysql options...]
#
# What it does: runs each query in docs/T5_SUPPLIER_PAYMENT_IMPACT_QUERIES.sql inside START TRANSACTION READ ONLY and writes one TSV per query.
# What it will NOT do: run without the explicit confirmation; run if it can write (it first proves the session is read-only by attempting a
# harmless write that MUST be refused); change any data. It performs no UPDATE/DELETE/INSERT/DDL. Results contain ids, document numbers and money only.
# Credentials: use a read-only database account via --defaults-extra-file=... (passed as a mysql option). Never run it on a production server.
set -u
DB="${1:-}"; OUT="${2:-}"; shift 2 2>/dev/null || true
MYSQL_OPTS=("$@")
HERE="$(cd "$(dirname "$0")/../.." && pwd)"
SQL="$HERE/docs/T5_SUPPLIER_PAYMENT_IMPACT_QUERIES.sql"

die() { echo "REFUSED: $*" >&2; exit 2; }
[ -n "$DB" ] && [ -n "$OUT" ] || die "usage: N1_CONFIRM_ISOLATED_RESTORE=yes $0 <database> <output-dir> [mysql options]"
[ "${N1_CONFIRM_ISOLATED_RESTORE:-}" = "yes" ] || die "set N1_CONFIRM_ISOLATED_RESTORE=yes to confirm that '$DB' is an isolated restored copy, not a live database"
[ -f "$SQL" ] || die "query file not found: $SQL"
command -v mysql >/dev/null || die "mysql client not found"

M=(mysql "${MYSQL_OPTS[@]}" --batch --raw "$DB")
mkdir -p "$OUT" || die "cannot create $OUT"

# 1. context (so the accountant's report states exactly what was queried)
{
  echo "run_at_utc	$(date -u +%FT%TZ)"
  "${M[@]}" -N -e "SELECT 'server', @@hostname; SELECT 'version', VERSION(); SELECT 'database', DATABASE(); SELECT 'server_read_only', @@read_only; SELECT 'user', CURRENT_USER();"
  echo "query_file_sha256	$(sha256sum "$SQL" | cut -d' ' -f1)"
} > "$OUT/00_context.tsv" 2>"$OUT/00_context.err" || die "cannot connect (see $OUT/00_context.err)"
cat "$OUT/00_context.tsv"

# 2. prove the session cannot write: a write inside a READ ONLY transaction must be refused (error 1792)
PROBE=$("${M[@]}" -e "START TRANSACTION READ ONLY; UPDATE suppliers SET id = id WHERE 1 = 0; ROLLBACK;" 2>&1)
case "$PROBE" in
  *1792*|*"READ ONLY transaction"*) echo "read-only probe: write refused as required" ;;
  *) die "the read-only probe did not refuse a write ($PROBE). Not running anything." ;;
esac

# 3. split the file into Q1..Q10 and run each in a READ ONLY transaction
TMP="$(mktemp -d)"; trap 'rm -rf "$TMP"' EXIT
awk -v dir="$TMP" '
  /^-- Q[0-9]+\./ { match($0, /Q[0-9]+/); name = sprintf("Q%02d", substr($0, RSTART+1, RLENGTH-1)); active = 1 }
  active && !/^[[:space:]]*--/ && NF { print > (dir "/" name ".sql"); if ($0 ~ /;[[:space:]]*$/) active = 0 }
' "$SQL"
fail=0
for n in 01 02 03 04 05 06 07 08 09 10; do
  f="$TMP/Q$n.sql"; [ -f "$f" ] || { echo "Q$n: not found in the query file" >&2; fail=1; continue; }
  if { echo "START TRANSACTION READ ONLY;"; cat "$f"; echo "ROLLBACK;"; } | "${M[@]}" > "$OUT/Q$n.tsv" 2> "$OUT/Q$n.err"; then
    echo "Q$n: $(( $(wc -l < "$OUT/Q$n.tsv") > 0 ? $(wc -l < "$OUT/Q$n.tsv") - 1 : 0 )) row(s) -> $OUT/Q$n.tsv"; rm -f "$OUT/Q$n.err"
  else
    echo "Q$n: FAILED (see $OUT/Q$n.err; Q5 needs the v2 journal tables and may legitimately fail on a database without them)" >&2; fail=1
  fi
done
echo "Finished. Nothing was written to the database. Give the TSV files and 00_context.tsv to the accountant; read docs/T5_N1_READONLY_AUDIT_RUNBOOK.md."
exit $fail
