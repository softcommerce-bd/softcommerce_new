<?php

namespace App\Services\Inventory;

use App\Services\Inventory\Exceptions\InsufficientPhysicalStock;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Runs an operation at most once per (scope, key) and returns the stored result on a repeat.
 * The key row is inserted inside the operation's own transaction: if the operation fails the key disappears with
 * the rollback, so a retry runs it again; if it succeeds the key and its effects commit together. Two concurrent
 * attempts serialise on the unique index; the second one reads the first one's stored result.
 */
class Idempotency
{
    /** @param callable():array $operation must return a JSON-serialisable array (ids, totals) */
    public static function once(string $scope, string $key, callable $operation): array
    {
        if ($key === '' || strlen($key) > 120) {
            throw new RuntimeException('An idempotency key (1-120 characters) is required.');
        }

        // 3 attempts: if the database picks this transaction as the deadlock victim it has rolled back completely (including the key row), so it is safe to run again
        return DB::transaction(function () use ($scope, $key, $operation) {
            try {
                DB::table('idempotency_keys')->insert(['scope' => $scope, 'key' => $key, 'created_at' => now(), 'updated_at' => now()]);
            } catch (QueryException $e) {
                $row = DB::table('idempotency_keys')->where('scope', $scope)->where('key', $key)->first();
                if ($row && $row->result !== null) {
                    $stored = json_decode($row->result, true) ?: [];
                    $stored['replayed'] = true;

                    return $stored;
                }
                throw $e;
            }

            $result = $operation();
            DB::table('idempotency_keys')->where('scope', $scope)->where('key', $key)->update(['result' => json_encode($result), 'updated_at' => now()]);
            $result['replayed'] = false;

            return $result;
        }, \App\Support\DeadlockRetry::MAX);
    }
}
