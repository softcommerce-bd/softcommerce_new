#!/usr/bin/env bash
# T-5: MySQL 8.4 compatibility rehearsal. PREPARED, NOT EXECUTED in the authoring environment (no MySQL 8.4 server was available:
# the only server was MariaDB 10.11.14 and the Docker daemon could not start). Run it against a DISPOSABLE database whose name ends in _test
# on a real MySQL 8.4 server. It refuses anything else. It never touches staging or production.
#
#   MYSQL84_HOST=127.0.0.1 MYSQL84_PORT=3307 MYSQL84_USER=root MYSQL84_PASS=... MYSQL84_DB=t5_mysql84_test tests/staging/mysql84-rehearsal.sh
set -euo pipefail
: "${MYSQL84_HOST:?}" "${MYSQL84_USER:?}" "${MYSQL84_DB:?}"
PORT="${MYSQL84_PORT:-3306}"
case "$MYSQL84_DB" in *_test) ;; *) echo "refusing: database name must end in _test"; exit 2;; esac
M=(mysql -h"$MYSQL84_HOST" -P"$PORT" -u"$MYSQL84_USER" ${MYSQL84_PASS:+-p"$MYSQL84_PASS"} -N -B)
VERSION=$("${M[@]}" -e "select version()")
echo "server version: $VERSION"
case "$VERSION" in 8.4.*) ;; *) echo "NOT MySQL 8.4 (got $VERSION): rehearsal is not valid"; exit 3;; esac
"${M[@]}" -e "create database if not exists \`$MYSQL84_DB\`"
export DB_CONNECTION=mysql DB_HOST="$MYSQL84_HOST" DB_PORT="$PORT" DB_USERNAME="$MYSQL84_USER" DB_PASSWORD="${MYSQL84_PASS:-}" DB_DATABASE="$MYSQL84_DB" APP_ENV=testing SERVER_NAME=localhost
echo "== 1. base schema + migrations (record the first error, if any)"
php artisan migrate --force
php artisan migrate --force --path=database/migrations/inventory_v2
echo "== 2. engines, unique keys, isolation (compare with docs/T5_MIGRATION_COMPATIBILITY_REPORT.md)"
"${M[@]}" -e "select @@transaction_isolation, @@innodb_lock_wait_timeout, @@sql_mode"
"${M[@]}" -e "select table_name, engine from information_schema.tables where table_schema='$MYSQL84_DB' and engine <> 'InnoDB'"
echo "== 3. T-5 index migration: up, rollback, up (repeat-safe)"
P=database/migrations/inventory_v2/2026_11_04_100000_add_checkout_and_lock_lookup_indexes.php
php artisan migrate:rollback --force --path=$P || true
php artisan migrate --force --path=$P
"${M[@]}" -e "select count(distinct table_name, index_name) from information_schema.statistics where table_schema='$MYSQL84_DB' and index_name like 'idx_t5_%'"
echo "== 4. regression + real-process concurrency (sequential, never overlapping)"
vendor/bin/phpunit
vendor/bin/phpunit -c phpunit.concurrency.xml
echo "== 5. audit queries syntax check (read-only)"
"${M[@]}" --force "$MYSQL84_DB" < docs/T5_PHASE0_AUDIT_QUERIES.sql > /dev/null
echo "rehearsal finished: attach this output to docs/T5_MIGRATION_COMPATIBILITY_REPORT.md; any first error is a compatibility finding"
