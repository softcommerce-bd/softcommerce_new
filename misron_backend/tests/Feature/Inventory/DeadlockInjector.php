<?php

namespace Tests\Feature\Inventory;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Makes the Nth..matching statement fail exactly like a deadlock victim (SQLSTATE 40001 / 1213), a fixed number of times, then stops.
 * The statement has already run when the exception is raised, so the transaction holds real partial work that the rollback must remove.
 */
class DeadlockInjector
{
    public int $thrown = 0;
    public int $seen = 0;

    public function __construct(private string $regex, private int $times)
    {
        DB::listen(function ($q) {
            if (preg_match($this->regex, $q->sql)) {
                $this->seen++;
                if ($this->thrown < $this->times) {
                    $this->thrown++;
                    // A real deadlock victim has already been rolled back by the server. Emulate that: undo everything done since the operation's own
                    // transaction began (the suite's wrapping transaction stays), then raise the same error.
                    $base = \App\Support\DeadlockRetry::$baseLevel;
                    if ($base > 0 && DB::transactionLevel() > $base) {
                        DB::connection()->unprepared('ROLLBACK TO SAVEPOINT trans' . ($base + 1));
                    }
                    $pdo = new \PDOException('SQLSTATE[40001]: Serialization failure: 1213 Deadlock found when trying to get lock; try restarting transaction');
                    $pdo->errorInfo = ['40001', 1213, 'Deadlock found when trying to get lock'];
                    throw new QueryException($q->sql, $q->bindings, $pdo);
                }
            }
        });
    }

    public static function once(string $regex): self
    {
        return new self($regex, 1);
    }

    public static function twice(string $regex): self
    {
        return new self($regex, 2);
    }

    public static function always(string $regex): self
    {
        return new self($regex, 1000);
    }
}
