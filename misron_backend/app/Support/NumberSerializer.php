<?php

namespace App\Support;

use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

/**
 * Serialises the creation of the FIRST document number of a period (no schema change, no new number format).
 *
 * Why: "latest row + 1" is read with a locking read over the voucher_no index. When no row of the period exists yet, InnoDB
 * locks the empty index gap instead of a row; every concurrent creator gets that gap lock, and each one's INSERT then waits
 * for the others' gap locks -> deadlock, one transaction is killed. A row lock cannot help because there is no row.
 *
 * What: a database-wide named lock (GET_LOCK) taken before the locking read and held until the OUTERMOST transaction ends
 * (commit or rollback), so the creators of one number series run one after the other.
 *
 * Safety conditions (documented in docs/T5_INCOME_NUMBER_DEADLOCK_REPORT.md):
 *  - it is deadlock-free only for a series whose generator is reached BEFORE any other contended row lock in the transaction
 *    (the named lock is invisible to InnoDB's deadlock detector). This is true for incomes; it is NOT yet true for the other
 *    series, so they do not use it (they need the sequence-table decision);
 *  - it assumes one session = one connection to the database server (no statement-multiplexing proxy);
 *  - if the lock cannot be had (timeout / unsupported) the caller carries on exactly as before and the deadlock retry still applies.
 */
final class NumberSerializer
{
    /** @var array<string,int> lock name => times acquired in this session */
    private static array $held = [];

    /** the event dispatcher (weakly held; object ids can be reused) the listeners are registered on (a new application instance, as in tests, has a new dispatcher) */
    private static ?\WeakReference $listeningOn = null;

    public static function hold(string $series, int $timeoutSeconds = 10): bool
    {
        if (DB::transactionLevel() <= DeadlockRetry::$baseLevel) {
            return false; // no open transaction of our own: nothing to hold the lock for
        }
        self::listen();
        $name = 'misron:number:' . $series;
        try {
            $row = DB::selectOne('SELECT GET_LOCK(?, ?) AS acquired', [$name, $timeoutSeconds]);
        } catch (\Throwable $e) {
            Log::warning('Number lock unavailable: ' . $e->getMessage());

            return false;
        }
        if ($row === null || (int) $row->acquired !== 1) {
            Log::warning('Number lock not obtained for ' . $series . ' within ' . $timeoutSeconds . 's; continuing without it');

            return false;
        }
        self::$held[$name] = (self::$held[$name] ?? 0) + 1;

        return true;
    }

    public static function releaseAll(): void
    {
        foreach (self::$held as $name => $count) {
            for ($i = 0; $i < $count; $i++) {
                try {
                    DB::selectOne('SELECT RELEASE_LOCK(?) AS released', [$name]);
                } catch (\Throwable $e) {
                    Log::warning('Number lock release failed: ' . $e->getMessage());
                    break;
                }
            }
        }
        self::$held = [];
    }

    public static function heldCount(): int
    {
        return array_sum(self::$held);
    }

    private static function listen(): void
    {
        $dispatcher = \Illuminate\Support\Facades\Event::getFacadeRoot();
        if (self::$listeningOn !== null && self::$listeningOn->get() === $dispatcher) {
            return;
        }
        self::$listeningOn = \WeakReference::create($dispatcher);
        $done = function ($event) {
            if (self::$held !== [] && $event->connection->transactionLevel() <= DeadlockRetry::$baseLevel) {
                self::releaseAll();
            }
        };
        Event::listen(TransactionCommitted::class, $done);
        Event::listen(TransactionRolledBack::class, $done);
    }
}
