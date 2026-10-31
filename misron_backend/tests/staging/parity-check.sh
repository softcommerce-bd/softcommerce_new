#!/bin/bash
# Proves the artisan migrations and sqlupdates/inventory_v2.sql produce the same database. Works on two NEW disposable copies of a
# pre-migration database (never on staging or production):  SOURCE_DB=<pre-migration db> tests/staging/parity-check.sh
set -u
cd "$(dirname "$0")/../.."; source /tmp/claude-0/env.sh
SRC=${SOURCE_DB:?set SOURCE_DB to a pre-migration database}; A=parity_artisan_test; B=parity_sql_test; OUT=${OUT:-/tmp/claude-0/parity}; mkdir -p $OUT
for d in $A $B; do mysql -e "DROP DATABASE IF EXISTS $d; CREATE DATABASE $d CHARACTER SET utf8mb4; GRANT ALL ON $d.* TO 't'@'127.0.0.1';"; mysqldump --single-transaction $SRC | mysql $d; done
echo "engine: $(mysql -N -e 'select version(), @@version_comment' | tr '\t' ' ')"
DB_DATABASE=$A php artisan migrate --path=database/migrations/inventory_v2 --force 2>&1 | grep -E "DONE|ERROR|Exception"
mysql $B < sqlupdates/inventory_v2.sql 2>&1 | tail -3; echo "sql script exit: $?"
for d in $A $B; do
 mysql -N -e "select table_name,column_name,column_type,is_nullable,coalesce(column_default,'NULL') from information_schema.columns where table_schema='$d' order by 1,2" > $OUT/cols_$d.txt
 mysql -N -e "select table_name,index_name,non_unique,group_concat(column_name order by seq_in_index) from information_schema.statistics where table_schema='$d' group by 1,2,3 order by 1,2" > $OUT/idx_$d.txt
 mysql -N -e "select table_name,engine from information_schema.tables where table_schema='$d' order by 1" > $OUT/tbl_$d.txt
 mysql -N $d -e "select code,name,type,sub_type,current_balance from chart_of_accounts where code like 'SYS-%' order by 1; select count(*) from permissions where section='purchasing_inventory'; select count(*) from role_has_permissions rp join permissions p on p.id=rp.permission_id where p.section='purchasing_inventory'; select migration from migrations where migration like '2026_11%' order by 1" > $OUT/misc_$d.txt
done
rc=0; for k in cols idx tbl misc; do if diff -q $OUT/${k}_$A.txt $OUT/${k}_$B.txt >/dev/null; then echo "$k: IDENTICAL ($(wc -l < $OUT/${k}_$A.txt) lines)"; else echo "$k: DIFFERENT"; diff $OUT/${k}_$A.txt $OUT/${k}_$B.txt | head -5; rc=1; fi; done
exit $rc
