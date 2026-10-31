<?php

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * T-5 F-03 (static): a controller must not answer (return a response/redirect) while its manually opened transaction is still open.
 * Such a return skipped the rollback, so row locks stayed held until the PHP process ended. Every such return must be preceded by DB::rollBack()/commit().
 * Heuristic (line based): from `DB::beginTransaction()` until the next `DB::commit()/rollBack()`, a line starting with `return response(`,
 * `return redirect`, `return back(` or `return view(` is a violation unless the previous code line is a rollBack/commit. Returns inside closures
 * (e.g. `return $stored;`) are not responses and are ignored.
 */
class NoResponseInsideOpenTransactionTest extends TestCase
{
    public function test_no_controller_returns_a_response_while_its_transaction_is_open()
    {
        $violations = [];
        $files = array_merge(glob(__DIR__ . '/../../app/Http/Controllers/*.php'), glob(__DIR__ . '/../../app/Http/Controllers/*/*.php'), glob(__DIR__ . '/../../app/Http/Controllers/*/*/*.php'));
        foreach ($files as $file) {
            $lines = file($file);
            $method = null;
            $open = false;
            $prev = '';
            foreach ($lines as $i => $line) {
                $t = trim($line);
                if ($t === '' || str_starts_with($t, '//') || str_starts_with($t, '*') || str_starts_with($t, '/*')) {
                    continue;
                }
                if (preg_match('/function\s+(\w+)\s*\(/', $t, $m) && preg_match('/^(public|protected|private)/', $t)) {
                    $method = $m[1];
                    $open = false;
                }
                if (str_contains($t, 'DB::beginTransaction()')) {
                    $open = true;
                }
                if (preg_match('/DB::(commit|rollBack|rollback)\(\)/', $t)) {
                    $open = false;
                }
                if ($open && preg_match('/^return\s+(response\(|redirect|back\(|view\()/', $t) && !preg_match('/DB::(commit|rollBack|rollback)\(\)/', $prev)) {
                    $violations[] = str_replace(realpath(__DIR__ . '/../..') . '/', '', realpath($file)) . '::' . $method . ' line ' . ($i + 1);
                }
                $prev = $t;
            }
        }
        $this->assertSame([], $violations, 'a response is returned inside an open transaction (roll back first)');
    }
}
