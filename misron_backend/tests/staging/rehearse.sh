#!/bin/bash
# Staging migration rehearsal. $S is the ONLY staging database and is never rolled back; rollback runs on a disposable copy.
set -u
cd "$(dirname "$0")/../.."; source /tmp/claude-0/env.sh; S=${STAGING_DB:-sc_staging}; R=${R:-/tmp/claude-0/staging}; mkdir -p $R   # STAGING_DB = the masked staging database (never production)
snap() { DB_DATABASE=$1 php artisan tinker --execute="echo json_encode(include 'tests/staging/baseline.php');" 2>/dev/null | tail -1 > $R/$2.json; }
copy() { mysql -e "DROP DATABASE IF EXISTS $2; CREATE DATABASE $2 CHARACTER SET utf8mb4; GRANT ALL ON $2.* TO 't'@'127.0.0.1';"; mysqldump --single-transaction --routines --triggers $1 | mysql $2; }
echo "== 1 baseline of masked staging (before anything)"; snap $S baseline_before
mysql -N $S < tests/staging/fingerprint.sql > $R/fp_baseline.txt
echo "== 2 disposable copies: sql path, pre-migration reference"; copy $S ${S}_sql; copy $S ${S}_pre
echo "== 3 InnoDB conversion (staging + both copies)"
for d in $S ${S}_sql ${S}_pre; do mysql $d < sqlupdates/inventory_v2_innodb_conversion.sql > $R/innodb_$d.txt 2>&1; done
mysql -N $S -e "select table_name, engine from information_schema.tables where table_schema=database() and table_name in ('purchases','purchase_products','purchase_returns','purchase_return_products','suppliers') order by 1" > $R/engines_after.txt
snap $S after_innodb
echo "== 4 artisan migrate on staging; SQL script on copy"
DB_DATABASE=$S php artisan migrate --path=database/migrations/inventory_v2 --force > $R/migrate_staging.txt 2>&1; grep -cE "DONE" $R/migrate_staging.txt
mysql ${S}_sql < sqlupdates/inventory_v2.sql > $R/sql_script.txt 2>&1; echo "sql script exit $?"
snap $S after_migrate; snap ${S}_sql after_migrate_sql
mysql -N $S < tests/staging/fingerprint.sql > $R/fp_after.txt
echo "== 5 rollback on a THIRD disposable copy of the SQL-path database"
copy ${S}_sql ${S}_rb; mysql ${S}_rb < sqlupdates/inventory_v2_rollback.sql > $R/rollback.txt 2>&1; snap ${S}_rb after_rollback
for d in staging:$S sql:${S}_sql rb:${S}_rb pre:${S}_pre; do n=${d%%:*}; db=${d##*:}
 mysql -N $db -e "select table_name,column_name,column_type,is_nullable,coalesce(column_default,'NULL') from information_schema.columns where table_schema=database() order by 1,2" > $R/cols_$n.txt
 mysql -N $db -e "select table_name,index_name,non_unique,group_concat(column_name order by seq_in_index) from information_schema.statistics where table_schema=database() group by 1,2,3 order by 1,2" > $R/idx_$n.txt
 mysql -N $db -e "select table_name,engine from information_schema.tables where table_schema=database() order by 1" > $R/tbl_$n.txt
 mysql -N $db -e "select code,name,type,sub_type,current_balance from chart_of_accounts where code like 'SYS-%' order by code; select count(*) from permissions where section='purchasing_inventory'; select count(*) from role_has_permissions rp join permissions p on p.id=rp.permission_id where p.section='purchasing_inventory'; select migration from migrations where migration like '2026_11%' order by 1" > $R/misc_$n.txt
done
echo DONE
