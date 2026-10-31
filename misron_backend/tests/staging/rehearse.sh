#!/bin/bash
# Staging migration rehearsal. sc_staging is the ONLY staging database and is never rolled back; rollback runs on a disposable copy.
set -u
cd "$(dirname "$0")/../.."; source /tmp/claude-0/env.sh; R=${R:-/tmp/claude-0/staging}; mkdir -p $R
snap() { DB_DATABASE=$1 php artisan tinker --execute="echo json_encode(include 'tests/staging/baseline.php');" 2>/dev/null | tail -1 > $R/$2.json; }
copy() { mysql -e "DROP DATABASE IF EXISTS $2; CREATE DATABASE $2 CHARACTER SET utf8mb4; GRANT ALL ON $2.* TO 't'@'127.0.0.1';"; mysqldump --single-transaction --routines --triggers $1 | mysql $2; }
echo "== 1 baseline of masked staging (before anything)"; snap sc_staging baseline_before
mysql -N sc_staging < tests/staging/fingerprint.sql > $R/fp_baseline.txt
echo "== 2 disposable copies: sql path, pre-migration reference"; copy sc_staging sc_stg_sql; copy sc_staging sc_stg_pre
echo "== 3 InnoDB conversion (staging + both copies)"
for d in sc_staging sc_stg_sql sc_stg_pre; do mysql $d < sqlupdates/inventory_v2_innodb_conversion.sql > $R/innodb_$d.txt 2>&1; done
mysql -N sc_staging -e "select table_name, engine from information_schema.tables where table_schema=database() and table_name in ('purchases','purchase_products','purchase_returns','purchase_return_products','suppliers') order by 1" > $R/engines_after.txt
snap sc_staging after_innodb
echo "== 4 artisan migrate on staging; SQL script on copy"
DB_DATABASE=sc_staging php artisan migrate --path=database/migrations/inventory_v2 --force > $R/migrate_staging.txt 2>&1; grep -cE "DONE" $R/migrate_staging.txt
mysql sc_stg_sql < sqlupdates/inventory_v2.sql > $R/sql_script.txt 2>&1; echo "sql script exit $?"
snap sc_staging after_migrate; snap sc_stg_sql after_migrate_sql
mysql -N sc_staging < tests/staging/fingerprint.sql > $R/fp_after.txt
echo "== 5 rollback on a THIRD disposable copy of the SQL-path database"
copy sc_stg_sql sc_stg_rb; mysql sc_stg_rb < sqlupdates/inventory_v2_rollback.sql > $R/rollback.txt 2>&1; snap sc_stg_rb after_rollback
for d in staging:sc_staging sql:sc_stg_sql rb:sc_stg_rb pre:sc_stg_pre; do n=${d%%:*}; db=${d##*:}
 mysql -N $db -e "select table_name,column_name,column_type,is_nullable,coalesce(column_default,'NULL') from information_schema.columns where table_schema=database() order by 1,2" > $R/cols_$n.txt
 mysql -N $db -e "select table_name,index_name,non_unique,group_concat(column_name order by seq_in_index) from information_schema.statistics where table_schema=database() group by 1,2,3 order by 1,2" > $R/idx_$n.txt
 mysql -N $db -e "select table_name,engine from information_schema.tables where table_schema=database() order by 1" > $R/tbl_$n.txt
 mysql -N $db -e "select code,name,type,sub_type,current_balance from chart_of_accounts where code like 'SYS-%' order by code; select count(*) from permissions where section='purchasing_inventory'; select count(*) from role_has_permissions rp join permissions p on p.id=rp.permission_id where p.section='purchasing_inventory'; select migration from migrations where migration like '2026_11%' order by 1" > $R/misc_$n.txt
done
echo DONE
