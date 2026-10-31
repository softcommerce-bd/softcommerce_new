<?php

namespace Tests\Feature\Inventory;

use App\Http\Middleware\RetryDeadlockedCheckout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** D. The legacy order paths (storefront API, POS, funnel) are protected by a bounded deadlock retry, and nothing else is retried. */
class DeadlockMiddlewareTest extends TestCase
{
    private function pass(array $answers): array
    {
        $calls = 0;
        $mw = new RetryDeadlockedCheckout();
        $resp = $mw->handle(Request::create('/x', 'POST'), function () use (&$calls, $answers) {
            return new JsonResponse($answers[min($calls++, count($answers) - 1)]);
        });

        return [$calls, json_decode($resp->getContent(), true)];
    }

    public function test_a_deadlock_answer_is_retried_until_it_succeeds()
    {
        [$calls, $final] = $this->pass([['result' => false, 'message' => 'Failed to place order: SQLSTATE[40001]: Serialization failure: 1213 Deadlock found when trying to get lock'], ['result' => false, 'message' => 'Server error occurred: Deadlock found'], ['result' => true]]);
        $this->assertSame([3, true], [$calls, $final['result']]);
    }

    public function test_the_retry_is_bounded_and_the_last_answer_is_returned_unchanged()
    {
        [$calls, $final] = $this->pass([['result' => false, 'message' => 'SQLSTATE[40001]: Deadlock found']]);
        $this->assertSame(RetryDeadlockedCheckout::MAX_ATTEMPTS, $calls);
        $this->assertStringContainsString('Deadlock', $final['message']);
    }

    public function test_the_funnels_busy_answer_is_retried_too()
    {
        [$calls] = $this->pass([['error' => ['code' => 'inventory_busy'], 'message' => 'Too many orders at once. Please try again.'], ['order' => ['id' => 1]]]);
        $this->assertSame(2, $calls);
    }

    public function test_refusals_and_successes_are_never_retried()
    {
        foreach ([['result' => true], ['result' => false, 'message' => 'SAMPLE just stock outs.'], ['success' => 0, 'message' => 'Please select a product.'], ['message' => 'The phone field is required.']] as $answer) {
            [$calls] = $this->pass([$answer]);
            $this->assertSame(1, $calls, json_encode($answer));
        }
    }

    public function test_the_middleware_is_attached_to_every_legacy_order_placement_route()
    {
        $want = ['api/v2/order/store', 'api/v2/guest/order/store', 'api/v2/funnels/{slug}/checkout', 'admin/pos-order', 'seller/pos-order'];
        $found = [];
        foreach (Route::getRoutes()->getRoutes() as $r) {
            foreach ($want as $uri) {
                if (str_ends_with($r->uri(), ltrim(str_replace('api/v2/', '', $uri), '/')) && in_array('POST', $r->methods(), true) && in_array('retry.deadlock', $r->gatherMiddleware(), true)) {
                    $found[$uri] = true;
                }
            }
        }
        $this->assertGreaterThanOrEqual(4, count($found), 'protected routes: ' . implode(', ', array_keys($found)));
    }
}
