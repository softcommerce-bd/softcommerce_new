#!/bin/bash
# Imports a production BACKUP FILE into a NEW isolated staging database, masks it, and proves masking changed no number.
#   SOURCE_DUMP=/path/to/backup.sql STAGING_DB=sc_staging_prod1 tests/staging/prepare-staging.sh
# Safety: never connects to a remote host, refuses names that do not look like staging, refuses an existing database.
set -eu
cd "$(dirname "$0")/../.."
: "${SOURCE_DUMP:?set SOURCE_DUMP to the backup file}"; : "${STAGING_DB:?set STAGING_DB to the new staging database name}"
case "$STAGING_DB" in sc_stg*|sc_staging*|*_staging|*_staging_*) ;; *) echo "REFUSED: '$STAGING_DB' does not look like a staging database name (sc_stg*, sc_staging*, *_staging)"; exit 2;; esac
case "$STAGING_DB" in misron|softcommerce|production|prod) echo "REFUSED: production-looking name"; exit 2;; esac
if [ -n "${MYSQL_HOST:-}" ] && [ "$MYSQL_HOST" != "localhost" ] && [ "$MYSQL_HOST" != "127.0.0.1" ]; then echo "REFUSED: this script only talks to a local staging server (MYSQL_HOST=$MYSQL_HOST)"; exit 2; fi
if mysql -N -e "select 1 from information_schema.schemata where schema_name='$STAGING_DB'" | grep -q 1; then echo "REFUSED: database $STAGING_DB already exists (never overwrite a staging database; choose a new name)"; exit 2; fi
OUT=${OUT:-/tmp/claude-0/staging-$STAGING_DB}; mkdir -p "$OUT"
echo "server: $(mysql -N -e 'select version(), @@version_comment' | tr '\t' ' ')"; head -20 "$SOURCE_DUMP" | grep -iE "server version|Generation Time|Host:" || true
mysql -e "CREATE DATABASE \`$STAGING_DB\` CHARACTER SET utf8mb4"
mysql "$STAGING_DB" < "$SOURCE_DUMP"
mysql -N "$STAGING_DB" < tests/staging/fingerprint.sql > "$OUT/fp_before_mask.txt"
mysql "$STAGING_DB" < tests/staging/mask-pii.sql
mysql -N "$STAGING_DB" < tests/staging/fingerprint.sql > "$OUT/fp_after_mask.txt"
diff "$OUT/fp_before_mask.txt" "$OUT/fp_after_mask.txt" && echo "masking changed no financial or stock number"
echo "staging database $STAGING_DB ready (flag stays OFF). Next: DB_DATABASE=$STAGING_DB tests/staging/rehearse.sh"
