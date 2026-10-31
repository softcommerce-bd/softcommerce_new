<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Bounded retry of a database operation that the database killed as a deadlock victim.
 *
 * Safe only when ALL of these hold, and the helper checks the ones it can:
 *  - the failure is a deadlock (MySQL 1213 / SQLSTATE 40001), not any other error;
 *  - the transaction has been rolled back completely: the connection is back at the transaction level the operation started at,
 *    and that level is the outermost one (a deadlock inside someone else's open transaction rolls THAT back too; it cannot be repeated from here);
 *  - fewer than MAX attempts were made (three in total, never more);
 *  - the repeated work has no non-transactional side effect, which the callers guarantee (files are created once per request and removed
 *    only after commit, no external call or notification is made inside a retried transaction, and every retried write sits behind an
 *    idempotency key or a unique key).
 */
final class DeadlockRetry
{
    public const MAX = 3;

    /** Test hook: the transaction level at which an operation starts (1 under the test suite's wrapping transaction). */
    public static int $baseLevel = 0;

    public static function isDeadlock(\Throwable $e): bool
    {
        for ($x = $e; $x !== null; $x = $x->getPrevious()) {
            $info = $x instanceof \Illuminate\Database\QueryException ? $x->errorInfo : (property_exists($x, 'errorInfo') ? $x->errorInfo : null);
            if (is_array($info) && (($info[0] ?? null) === '40001' || ($info[1] ?? null) === 1213)) {
                return true;
            }
            if (stripos($x->getMessage(), 'Deadlock found') !== false || str_contains($x->getMessage(), 'SQLSTATE[40001]')) {
                return true;
            }
        }

        return false;
    }

    /** May the operation that started at $entryLevel be run again after $e? */
    public static function canRetry(int $attempt, int $entryLevel, \Throwable $e): bool
    {
        return $attempt < self::MAX && $entryLevel === self::$baseLevel && DB::transactionLevel() <= $entryLevel && self::isDeadlock($e);
    }

    /** @param int $attempt the attempt that just failed (1, 2): the wait grows so that several victims of the same collision stop colliding again */
    public static function pause(int $attempt = 1): void
    {
        usleep(random_int(40, 160) * max(1, $attempt) * 1000); // desynchronise the victims
    }

    /**
     * Runs $work (which opens and closes its own transaction) up to MAX times while it keeps dying as a deadlock victim.
     * $afterRollback($e) runs after every failed attempt, before the decision: use it to give back anything a rollback cannot (MyISAM batches).
     */
    public static function run(callable $work, ?callable $afterRollback = null)
    {
        $entry = DB::transactionLevel();
        for ($attempt = 1; ; $attempt++) {
            try {
                return $work($attempt);
            } catch (\Throwable $e) {
                if ($afterRollback) {
                    $afterRollback($e);
                }
                if (!self::canRetry($attempt, $entry, $e)) {
                    throw $e;
                }
                self::pause($attempt);
            }
        }
    }
}
