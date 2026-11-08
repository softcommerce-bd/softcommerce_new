<?php

namespace App\Console\Commands;

use App\Support\NumberSerializer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Diagnostic for decision INC.1: does THIS application, talking to THIS database through its real connection path, get the one-request =
 * one-connection behaviour that NumberSerializer (the income-number lock) relies on? It writes NOTHING: it opens an empty transaction, takes
 * one throw-away named lock ("misron:number:selfcheck:<random>", which names no table or row) and releases it.
 * Run it the way the application runs (same host, same .env, same PHP-FPM pool user if possible): php artisan accounts:number-lock-selfcheck
 */
class NumberLockSelfCheck extends Command
{
    protected $signature = 'accounts:number-lock-selfcheck {--repeat=6 : how many times the connection id is sampled inside one transaction}';
    protected $description = 'Read-only check that a transaction keeps one database connection and that the income-number lock is visible to other connections';

    public function handle(): int
    {
        $ok = true;
        $cfg = config('database.connections.' . config('database.default'));
        $this->line('driver=' . ($cfg['driver'] ?? '?') . ' host=' . ($cfg['host'] ?? '?') . ' port=' . ($cfg['port'] ?? '?') . ' database=' . ($cfg['database'] ?? '?'));
        if (($cfg['driver'] ?? '') !== 'mysql') {
            $this->error('FAIL: the lock uses MySQL/MariaDB GET_LOCK; this connection is not mysql.');

            return self::FAILURE;
        }

        $info = DB::selectOne('SELECT VERSION() AS v, @@hostname AS h, @@port AS p, @@read_only AS ro, CONNECTION_ID() AS c');
        $this->line("server version={$info->v} hostname={$info->h} port={$info->p} read_only={$info->ro}");
        $wsrep = DB::select("SHOW VARIABLES LIKE 'wsrep_on'");
        $this->line('wsrep_on=' . ($wsrep ? $wsrep[0]->Value : '(not present)') . '   [ON = Galera/multi-primary: GET_LOCK is node-local, NOT safe]');
        $this->line('persistent_connection_option=' . json_encode($cfg['options'][\PDO::ATTR_PERSISTENT] ?? false) . '   [must be false]');
        if (($cfg['options'][\PDO::ATTR_PERSISTENT] ?? false)) {
            $this->warn('WARN: persistent connections are configured.');
            $ok = false;
        }
        if ($info->ro) {
            $this->warn('WARN: this server reports read_only=1 (a replica?). Writes must go to the primary.');
            $ok = false;
        }
        if ($wsrep && strtoupper((string) $wsrep[0]->Value) === 'ON') {
            $this->warn('WARN: Galera/multi-primary detected.');
            $ok = false;
        }

        // 1. one transaction = one connection id (a statement-multiplexing proxy would show several)
        DB::beginTransaction();
        try {
            $ids = [];
            for ($i = 0; $i < max(2, (int) $this->option('repeat')); $i++) {
                $ids[] = (int) DB::selectOne('SELECT CONNECTION_ID() AS c')->c;
                usleep(20000);
            }
            $distinct = array_values(array_unique($ids));
            $this->line('connection ids sampled inside one transaction: ' . implode(',', $ids));
            if (count($distinct) !== 1) {
                $this->error('FAIL: the connection id changed inside one transaction - a proxy/pool is multiplexing statements. The income-number lock cannot work.');
                $ok = false;
            } else {
                $this->info('PASS: one connection for the whole transaction.');
            }

            // 2. the lock taken here is visible to a second, independent connection, and is released by the end of the transaction
            $series = 'selfcheck:' . bin2hex(random_bytes(6));
            $held = NumberSerializer::hold($series, 2);
            $other = new \PDO(sprintf('mysql:host=%s;port=%s;dbname=%s', $cfg['host'], $cfg['port'], $cfg['database']), $cfg['username'], $cfg['password']);
            $blocked = !(int) $other->query("SELECT GET_LOCK('misron:number:" . $series . "', 0)")->fetchColumn();
            if ($held && $blocked) {
                $this->info('PASS: the lock is held and a second connection is refused.');
            } else {
                $this->error('FAIL: lock held=' . var_export($held, true) . ', second connection refused=' . var_export($blocked, true));
                $ok = false;
            }
            if (!$blocked) {
                $other->query("SELECT RELEASE_LOCK('misron:number:" . $series . "')")->fetchColumn();
            }
        } finally {
            DB::rollBack(); // nothing was written; the rollback also fires the release
        }
        if (isset($other, $series)) {
            $free = (int) $other->query("SELECT GET_LOCK('misron:number:" . $series . "', 0)")->fetchColumn();
            if ($free) {
                $other->query("SELECT RELEASE_LOCK('misron:number:" . $series . "')")->fetchColumn();
                $this->info('PASS: the lock was released when the transaction ended.');
            } else {
                $this->error('FAIL: the lock is still held after the transaction ended.');
                $ok = false;
            }
        }
        $this->line($ok ? 'RESULT: PASS (does not replace the DBA checklist: proxy/pool settings must still be confirmed from the infrastructure side).' : 'RESULT: FAIL - see above; the income number then relies on the deadlock retry alone (the behaviour before the fix).');

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
