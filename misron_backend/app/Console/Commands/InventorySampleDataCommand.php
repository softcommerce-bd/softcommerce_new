<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\User;
use App\Services\Inventory\InventoryAudit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Fills a TEST database with a small, clean and consistent catalogue: products with variations,
 * a discount, two purchase batches at different costs, a last unit and an empty shelf, each with
 * stock rows, cost batches and cached totals that agree exactly. It is what the end-to-end tests
 * and manual checks of the sales funnel builder sell from.
 *
 *   php artisan inventory:sample-data --database=misron_e2e_test
 *
 * Refuses any database whose name does not end in "_test". Safe to run twice: products are matched
 * by name and left alone when they exist.
 */
class InventorySampleDataCommand extends Command
{
    protected $signature = 'inventory:sample-data {--database= : A test database (name ends in _test)}';

    protected $description = 'Fill a test database with a clean, consistent sample catalogue and stock';

    private const PREFIX = 'SAMPLE ';

    public function handle(): int
    {
        $database = (string) $this->option('database');
        if ($database === '' || substr($database, -5) !== '_test') {
            $this->error('Give a test database with --database=NAME (the name must end in _test). Nothing was changed.');

            return self::FAILURE;
        }

        config(['database.connections.' . config('database.default') . '.database' => $database]);
        DB::purge();
        DB::connection()->getPdo();

        $owner = User::where('user_type', 'admin')->orderBy('id')->first() ?: $this->makeOwner();
        $category = $this->category('Sample Kitchen');
        $fashion = $this->category('Sample Fashion');
        $supplierId = $this->supplier();
        $this->accounts();
        $this->areas();

        $created = 0;
        foreach ($this->catalogue() as $spec) {
            if (Product::where('name', self::PREFIX . $spec['name'])->exists()) {
                continue;
            }
            $this->makeProduct($spec, $owner->id, $spec['category'] === 'fashion' ? $fashion : $category, $supplierId);
            $created++;
        }

        $this->info("{$created} sample product(s) created in {$database}.");
        $this->verify($database);

        return self::SUCCESS;
    }

    /** @return array<int, array<string, mixed>> */
    private function catalogue(): array
    {
        return [
            ['name' => 'Blender 1000W', 'category' => 'kitchen', 'price' => 4500, 'batches' => [['' => [40, 2800]]]],
            ['name' => 'Travel Mug', 'category' => 'kitchen', 'price' => 850, 'discount' => 10, 'batches' => [['' => [120, 400]]]],
            ['name' => 'Rice Cooker', 'category' => 'kitchen', 'price' => 3900, 'batches' => [['' => [10, 2400]], ['' => [8, 2600]]]],
            ['name' => 'Gift Mug Set', 'category' => 'kitchen', 'price' => 1500, 'batches' => [['' => [60, 700]]]],
            ['name' => 'Cotton T-Shirt', 'category' => 'fashion', 'price' => 1200, 'batches' => [['S' => [25, 500], 'M' => [25, 500], 'L' => [25, 500], 'XL' => [25, 500]]]],
            ['name' => 'Running Sneakers', 'category' => 'fashion', 'price' => 3200, 'batches' => [['40' => [15, 1900], '41' => [15, 1900], '42' => [15, 1900], '43' => [15, 1900]]]],
            ['name' => 'Limited Edition Watch', 'category' => 'fashion', 'price' => 999, 'batches' => [['' => [1, 500]]]],
            ['name' => 'Sold Out Lamp', 'category' => 'kitchen', 'price' => 1750, 'batches' => [['' => [0, 900]]]],
        ];
    }

    private function makeProduct(array $spec, int $ownerId, int $categoryId, int $supplierId): void
    {
        $name = self::PREFIX . $spec['name'];
        $variants = array_keys($spec['batches'][0]);
        $hasVariants = $variants !== [''];

        DB::transaction(function () use ($spec, $name, $ownerId, $categoryId, $supplierId, $variants, $hasVariants) {
            $product = new Product();
            $product->forceFill([
                'name' => $name,
                'added_by' => 'admin',
                'user_id' => $ownerId,
                'category_id' => $categoryId,
                'unit_price' => $spec['price'],
                'published' => 1,
                'approved' => 1,
                'digital' => 0,
                'auction_product' => 0,
                'wholesale_product' => 0,
                'discount' => $spec['discount'] ?? 0,
                'discount_type' => 'percent',
                'min_qty' => 1,
                'variant_product' => $hasVariants ? 1 : 0,
                'slug' => Str::slug($name) . '-' . Str::lower(Str::random(4)),
                'cash_on_delivery' => 1,
                'num_of_sale' => 0,
            ])->save();

            $totalQty = 0;
            foreach ($variants as $variant) {
                $qty = 0;
                foreach ($spec['batches'] as $batch) {
                    $qty += $batch[$variant][0];
                }
                ProductStock::create(['product_id' => $product->id, 'variant' => (string) $variant, 'sku' => strtoupper(Str::slug($name)) . ($variant !== '' ? '-' . $variant : ''), 'price' => $spec['price'], 'qty' => $qty]);
                $totalQty += $qty;
            }

            // One purchase per batch round, so the product has real cost history that adds up to its shelf.
            foreach ($spec['batches'] as $round => $batch) {
                $purchaseId = DB::table('purchases')->insertGetId([
                    'supplier_id' => $supplierId,
                    'reference_no' => 'SAMPLE-' . $product->id . '-' . ($round + 1),
                    'purchase_date' => now()->subDays(10 - $round)->toDateString(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                foreach ($batch as $variant => [$qty, $cost]) {
                    DB::table('purchase_products')->insert([
                        'product_id' => $product->id,
                        'purchase_id' => $purchaseId,
                        'product_name' => $name . ($variant !== '' ? " ({$variant})" : ''),
                        'variant' => $variant === '' ? null : (string) $variant,
                        'purchase_quantity' => (string) $qty,
                        'remaining_qty' => $qty,
                        'buying_unit_cost' => (string) $cost,
                        'total_cost' => (string) ($qty * $cost),
                        'product_tax' => '0',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            $product->current_stock = $totalQty;
            $product->save();
        });
    }

    private function makeOwner(): User
    {
        $user = new User();
        $user->forceFill(['name' => 'Sample Owner', 'email' => 'sample-owner@example.test', 'password' => bcrypt(Str::random(24)), 'user_type' => 'admin', 'banned' => 0])->save();

        return $user;
    }

    private function category(string $name): int
    {
        $existing = Category::where('name', $name)->value('id');
        if ($existing) {
            return (int) $existing;
        }
        $category = new Category();
        $category->forceFill(['name' => $name, 'slug' => Str::slug($name), 'level' => 0])->save();

        return (int) $category->id;
    }

    private function supplier(): int
    {
        $id = DB::table('suppliers')->where('business_name', 'Sample Supplier')->value('id');

        return (int) ($id ?: DB::table('suppliers')->insertGetId(['business_name' => 'Sample Supplier', 'mobile' => '01700000000', 'opening_balance' => '0', 'created_at' => now(), 'updated_at' => now()]));
    }

    /** The accounts the purchase and order screens post to. A live shop already has them. */
    private function accounts(): void
    {
        foreach ([
            ['1-15001', 'Inventory', 'asset', 'inventory'],
            ['2-21001', 'Accounts Payable', 'liability', 'payable'],
            ['1-13001', 'Accounts Receivable', 'asset', 'receivable'],
            ['4-41001', 'Sales', 'income', 'sales'],
            ['6-61000', 'Cost of Goods Sold', 'expense', 'cost_of_sales'],
        ] as [$code, $name, $type, $sub]) {
            if (!DB::table('chart_of_accounts')->where('name', $name)->where('type', $type)->exists() && !DB::table('chart_of_accounts')->where('code', $code)->exists()) {
                DB::table('chart_of_accounts')->insert(['code' => $code, 'name' => $name, 'type' => $type, 'sub_type' => $sub, 'status' => 'active', 'is_deleted' => 0, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    /** Delivery areas for the order form. */
    private function areas(): void
    {
        foreach (['Sample Dhaka City' => 60, 'Sample Outside Dhaka' => 120] as $name => $cost) {
            if (!DB::table('cities')->where('name', $name)->exists()) {
                DB::table('cities')->insert(['name' => $name, 'state_id' => 1, 'cost' => $cost, 'status' => 1]);
            }
        }
    }

    /** The catalogue must agree with itself; say so, or say what does not. */
    private function verify(string $database): void
    {
        $connection = DB::connection();
        $audit = new InventoryAudit($connection);
        $variants = $audit->variantRows();
        $products = $audit->productRows($variants);
        $mine = array_filter($products, function ($p) {
            return strpos((string) $p['name'], self::PREFIX) === 0;
        });
        $bad = array_filter($mine, function ($p) {
            return $p['hold'] !== InventoryAudit::HOLD_NONE;
        });

        $this->line(count($mine) . ' sample products checked: ' . (count($bad) === 0 ? 'stock rows, cost batches and cached totals agree.' : count($bad) . ' disagree.'));
        foreach ($bad as $p) {
            $this->warn("  {$p['name']}: {$p['hold']} (stock {$p['stock_total']}, batches {$p['fifo_total']})");
        }
    }
}
