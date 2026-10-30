<?php

namespace Tests\Funnel;

use App\Models\Product;
use App\Models\ProductStock;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Base for funnel tests that touch the database. Every test runs inside a
 * transaction that is rolled back, against the separate test database only.
 */
abstract class FunnelTestCase extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $database = DB::connection()->getDatabaseName();
        if (substr($database, -5) !== '_test') {
            $this->fail("Funnel tests must run against a *_test database, not '{$database}'. See tests/create_test_database.php.");
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function makeUser(string $type = 'staff', array $permissions = []): User
    {
        $user = new User();
        $user->forceFill([
            'name' => ucfirst($type) . ' ' . Str::random(5),
            'email' => Str::random(12) . '@funnel.test',
            'password' => bcrypt('secret'),
            'user_type' => $type,
            'banned' => 0,
        ])->save();

        if ($permissions !== []) {
            $role = Role::create(['name' => 'Funnel test ' . Str::random(8), 'guard_name' => 'web']);
            $role->givePermissionTo($permissions);
            $user->assignRole($role);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    }

    protected function makeAdmin(): User
    {
        $user = $this->makeUser('admin');
        $user->assignRole(Role::findByName('Super Admin', 'web'));

        return $user->fresh();
    }

    /**
     * A catalogue product with one stock row per entry of $stocks (a single
     * unnamed row when none is given). Rolled back with the test.
     */
    protected function makeProduct(array $attributes = [], array $stocks = []): Product
    {
        $product = new Product();
        $product->forceFill(array_merge([
            'name' => 'Product ' . Str::random(4),
            'added_by' => 'admin',
            'user_id' => 1,
            'unit_price' => 1000,
            'published' => 1,
            'approved' => 1,
            'digital' => 0,
            'auction_product' => 0,
            'wholesale_product' => 0,
            'discount' => 0,
            'discount_type' => 'amount',
            'min_qty' => 1,
            'variant_product' => count($stocks) > 1 ? 1 : 0,
            'slug' => 'product-' . Str::lower(Str::random(8)),
        ], $attributes));
        $product->save();

        foreach ($stocks ?: [[]] as $stock) {
            ProductStock::create(array_merge(['product_id' => $product->id, 'variant' => '', 'price' => $product->unit_price, 'qty' => 10], $stock));
        }

        return $product->fresh(['stocks']);
    }

    /** Makes sure a product with this id exists (for tests that name ids). */
    protected function ensureProduct(int $id): Product
    {
        return Product::find($id) ?: $this->makeProduct(['id' => $id, 'name' => "Product {$id}"]);
    }

    /** A publishable page: hero section, one product offer and a checkout. */
    protected function sellingPage(int $productId = 1, string $headline = 'Blender Mega Offer'): array
    {
        $this->ensureProduct($productId);

        return [
            'schemaVersion' => 1,
            'page' => [
                'sections' => [
                    [
                        'id' => 'hero_001',
                        'type' => 'section',
                        'children' => [
                            ['id' => 'heading_001', 'type' => 'heading', 'props' => ['text' => $headline, 'level' => 'h1']],
                        ],
                    ],
                    [
                        'id' => 'offers_001',
                        'type' => 'product_offer_selector',
                        'props' => ['selectionMode' => 'multiple'],
                        'productBindings' => [
                            [
                                'key' => 'offer_one',
                                'items' => [['productId' => $productId, 'quantity' => 1]],
                                'offer' => ['type' => 'percent', 'value' => 10],
                            ],
                        ],
                    ],
                    ['id' => 'checkout_001', 'type' => 'checkout_form', 'children' => [['id' => 'buy_001', 'type' => 'purchase_button', 'props' => ['text' => 'Order now']]]],
                ],
            ],
        ];
    }

    /** Clears resolved guards so the next request authenticates afresh. */
    protected function forgetAuth(): void
    {
        $this->app['auth']->forgetGuards();
    }
}
