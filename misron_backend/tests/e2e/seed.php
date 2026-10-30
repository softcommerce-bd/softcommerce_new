<?php

/*
|--------------------------------------------------------------------------
| Fixtures for the funnel editor's end-to-end tests
|--------------------------------------------------------------------------
|
| Called by the Playwright suite in misron_frontend (tests/funnel-editor).
| Works only against a *_test database and prints JSON on stdout.
|
|   php tests/e2e/seed.php users
|   php tests/e2e/seed.php funnel "<title>" [<content json file>]
|   php tests/e2e/seed.php handoff <email> <funnel id>
|   php tests/e2e/seed.php show <funnel id>
|   php tests/e2e/seed.php touch <funnel id>     (saves the draft as another editor would)
|   php tests/e2e/seed.php checkout-setup        (delivery areas and purchase batches for the E2E products)
|   php tests/e2e/seed.php publish <funnel id>
|   php tests/e2e/seed.php orders [<funnel id>]  (orders placed through funnels)
|   php tests/e2e/seed.php product <name> <price> <qty> [<discount %>]
|   php tests/e2e/seed.php stock <product id> | set-stock <product id> <variant> <qty> | set-price <product id> <price>
|   php tests/e2e/seed.php registry              (block registry JSON, for unit test fixtures)
|
*/

// Same environment as tests/e2e/router.php, so URLs built here match the
// running test servers.
$overrides = [
    'DB_DATABASE' => getenv('E2E_DB_DATABASE') ?: 'misron_e2e_test',
    'APP_URL' => getenv('E2E_APP_URL') ?: 'http://127.0.0.1:8001',
    // A cache folder of its own: settings read from the test database
    // must not leak into the development site, nor the other way round.
    'CACHE_DRIVER' => 'file',
    'CACHE_FILE_PATH' => dirname(__DIR__, 2) . '/storage/framework/cache/e2e',
    'FUNNEL_EDITOR_URL' => getenv('E2E_EDITOR_URL') ?: 'http://localhost:3100',
    'FUNNEL_PUBLIC_URL' => getenv('E2E_EDITOR_URL') ?: 'http://localhost:3100',
    // The storefront is told to refresh a page the moment it changes.
    'FUNNEL_REVALIDATE_URL' => (getenv('E2E_EDITOR_URL') ?: 'http://localhost:3100') . '/api/funnel/revalidate',
    'FUNNEL_REVALIDATE_SECRET' => 'e2e-revalidate-secret',
];
foreach ($overrides as $key => $value) {
    putenv("{$key}={$value}");
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Funnel;
use App\Models\User;
use App\Services\Funnel\EditorSession;
use App\Services\Funnel\FunnelService;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

if (substr(DB::connection()->getDatabaseName(), -5) !== '_test') {
    fwrite(STDERR, "Refusing to seed a database whose name does not end in _test.\n");
    exit(1);
}

$out = function (array $data) {
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
    exit(0);
};

$fail = function (string $message) {
    fwrite(STDERR, $message . "\n");
    exit(1);
};

$user = function (string $email, string $type, array $permissions = [], bool $superAdmin = false) {
    $user = User::where('email', $email)->first() ?: new User();
    $user->forceFill([
        'name' => ucfirst(strtok($email, '@')),
        'email' => $email,
        'password' => bcrypt('e2e-password'),
        'user_type' => $type,
        'banned' => 0,
    ])->save();

    $roleName = 'E2E ' . $email;
    if ($superAdmin) {
        $user->syncRoles([Role::findByName('Super Admin', 'web')]);
    } elseif ($permissions !== []) {
        $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        $role->syncPermissions(Permission::whereIn('name', $permissions)->get());
        $user->syncRoles([$role]);
    } else {
        $user->syncRoles([]);
    }

    return $user;
};

$command = $argv[1] ?? '';

switch ($command) {
    case 'users':
        $users = [
            'admin' => $user('e2e-admin@funnel.test', 'admin', [], true),
            'editor' => $user('e2e-editor@funnel.test', 'staff', ['view_funnels', 'edit_funnel']),
            'viewer' => $user('e2e-viewer@funnel.test', 'staff', ['view_funnels']),
            'customer' => $user('e2e-customer@funnel.test', 'customer'),
        ];
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $out(array_map(function (User $u) {
            return ['id' => $u->id, 'email' => $u->email];
        }, $users));

    case 'funnel':
        $admin = User::where('email', 'e2e-admin@funnel.test')->first() ?: $fail('Run "users" first.');
        $data = ['title' => $argv[2] ?? 'E2E funnel'];
        if (!empty($argv[3])) {
            $data['content'] = json_decode(file_get_contents($argv[3]), true);
        }
        $funnel = app(FunnelService::class)->create($data, $admin);
        $out(['id' => $funnel->id, 'slug' => $funnel->slug, 'revision' => $funnel->draft_revision]);

    case 'handoff':
        $who = User::where('email', $argv[2] ?? '')->first() ?: $fail('Unknown user.');
        $funnel = Funnel::find((int) ($argv[3] ?? 0)) ?: $fail('Unknown funnel.');
        $session = app(EditorSession::class);
        $code = $session->issueHandoff($who, $funnel);
        $out(['code' => $code, 'url' => $session->editorUrl($funnel, $code)]);

    case 'show':
        $funnel = Funnel::with('publishedVersion')->find((int) ($argv[2] ?? 0)) ?: $fail('Unknown funnel.');
        $out([
            'id' => $funnel->id,
            'status' => $funnel->status,
            'revision' => $funnel->draft_revision,
            'content' => $funnel->draft_content,
            'published_version' => $funnel->publishedVersion ? $funnel->publishedVersion->version_number : null,
            'published_content' => $funnel->publishedVersion ? $funnel->publishedVersion->content : null,
        ]);

    case 'products':
        // Eight blenders in a "Blenders" category (two with variations, one of
        // them nearly sold out), a hidden prototype and a digital manual, for
        // the offer-builder tests. Safe to call repeatedly.
        $existing = DB::table('product_stocks')->where('sku', 'BLD-001')->value('product_id');
        if (!$existing) {
            $category = new App\Models\Category();
            $category->forceFill(['name' => 'E2E Blenders', 'slug' => 'e2e-blenders', 'level' => 0])->save();
            $brand = App\Models\Brand::create(['name' => 'E2E Walton', 'slug' => 'e2e-walton']);
            $image = new App\Models\Upload();
            $image->forceFill(['file_original_name' => 'blender', 'file_name' => 'uploads/all/e2e-blender.png', 'extension' => 'png', 'type' => 'image', 'file_size' => 1000, 'user_id' => 1, 'external_link' => rtrim(getenv('E2E_APP_URL') ?: 'http://127.0.0.1:8001', '/') . '/assets/img/app.png'])->save();

            $make = function (string $name, float $price, array $stocks, array $extra = []) use ($category, $brand, $image) {
                $product = new App\Models\Product();
                $product->forceFill($extra + [
                    'name' => $name, 'added_by' => 'admin', 'user_id' => 1, 'category_id' => $category->id, 'brand_id' => $brand->id,
                    'thumbnail_img' => (string) $image->id, 'photos' => $image->id . ',' . $image->id,
                    'unit_price' => $price, 'discount' => 0, 'discount_type' => 'amount', 'published' => 1, 'approved' => 1,
                    'digital' => 0, 'auction_product' => 0, 'wholesale_product' => 0, 'min_qty' => 1,
                    'variant_product' => count($stocks) > 1 ? 1 : 0, 'current_stock' => array_sum(array_column($stocks, 'qty')),
                    'slug' => 'e2e-' . strtolower(str_replace(' ', '-', $name)),
                ])->save();
                foreach ($stocks as $stock) {
                    App\Models\ProductStock::create($stock + ['product_id' => $product->id, 'variant' => '', 'price' => $price]);
                }

                return $product;
            };

            $ids = [];
            foreach (range(1, 8) as $n) {
                $price = 3000 + $n * 500;
                $stocks = [['sku' => sprintf('BLD-%03d', $n), 'qty' => 25]];
                if ($n === 2) {
                    $stocks = [['sku' => 'BLD-002-1L', 'variant' => '1L', 'qty' => 10], ['sku' => 'BLD-002-2L', 'variant' => '2L', 'qty' => 0, 'price' => $price + 500]];
                }
                if ($n === 4) {
                    $stocks = [['sku' => 'BLD-004', 'qty' => 2]];
                }
                $ids[] = $make("E2E Blender {$n}", $price, $stocks, $n === 3 ? ['discount' => 10, 'discount_type' => 'percent'] : [])->id;
            }
            $make('E2E Blender Prototype', 9999, [['sku' => 'BLD-PROTO', 'qty' => 1]], ['published' => 0]);
            $make('E2E Blender Manual', 99, [['sku' => 'BLD-PDF', 'qty' => 1]], ['digital' => 1]);
            $make('E2E Travel Mug', 400, [['sku' => 'MUG-001', 'qty' => 50]]);
        }
        $out([
            'category_id' => DB::table('categories')->where('slug', 'e2e-blenders')->value('id'),
            'blenders' => DB::table('products')->where('name', 'like', 'E2E Blender %')->whereNotIn('name', ['E2E Blender Prototype', 'E2E Blender Manual'])->orderBy('id')->pluck('id')->all(),
            'mug' => DB::table('products')->where('name', 'E2E Travel Mug')->value('id'),
        ]);

    case 'checkout-setup':
        // Two delivery areas and a purchase batch behind every E2E product's stock,
        // so orders can be taken through the shared stock service. Safe to repeat.
        $areas = [];
        foreach ([['E2E Inside Dhaka', 80], ['E2E Outside Dhaka', 140]] as [$name, $cost]) {
            $id = DB::table('cities')->where('name', $name)->value('id')
                ?: DB::table('cities')->insertGetId(['name' => $name, 'state_id' => 1, 'cost' => $cost, 'status' => 1]);
            DB::table('cities')->where('id', $id)->update(['cost' => $cost, 'status' => 1]);
            $areas[$name] = $id;
        }
        $made = 0;
        foreach (DB::table('product_stocks')->join('products', 'products.id', '=', 'product_stocks.product_id')->where('products.name', 'like', 'E2E %')->get(['product_stocks.product_id', 'product_stocks.variant', 'product_stocks.qty', 'products.name']) as $row) {
            if (DB::table('purchase_products')->where('product_id', $row->product_id)->where('variant', $row->variant)->exists() || $row->qty < 1) {
                continue;
            }
            $purchase = DB::table('purchases')->insertGetId(['supplier_id' => 1, 'reference_no' => 'E2E-' . $row->product_id . '-' . substr(md5($row->variant), 0, 4), 'purchase_date' => date('Y-m-d'), 'created_at' => now(), 'updated_at' => now()]);
            DB::table('purchase_products')->insert([
                'product_id' => $row->product_id, 'purchase_id' => $purchase, 'product_name' => $row->name, 'variant' => $row->variant,
                'purchase_quantity' => (string) $row->qty, 'remaining_qty' => $row->qty, 'buying_unit_cost' => '1000', 'total_cost' => (string) ($row->qty * 1000),
                'product_tax' => '0', 'created_at' => now(), 'updated_at' => now(),
            ]);
            $made++;
        }
        $out(['areas' => $areas, 'batches_created' => $made]);

    case 'publish':
        $admin = User::where('email', 'e2e-admin@funnel.test')->first() ?: $fail('Run "users" first.');
        $funnel = Funnel::find((int) ($argv[2] ?? 0)) ?: $fail('Unknown funnel.');
        [$funnel, $version, $result] = app(FunnelService::class)->publish($funnel, $admin);
        $out(['slug' => $funnel->slug, 'version' => $version->version_number, 'warnings' => array_column($result->warnings(), 'code')]);

    case 'orders':
        // Orders placed through funnels (optionally one funnel), with their lines.
        $query = DB::table('orders')->whereNotNull('funnel_id')->orderBy('id');
        if (!empty($argv[2])) {
            $query->where('funnel_id', (int) $argv[2]);
        }
        $orders = $query->get()->map(function ($o) {
            $lines = DB::table('order_details')->where('order_id', $o->id)->orderBy('id')->get(['product_id', 'variation', 'quantity', 'price', 'tax', 'shipping_cost', 'buying_price', 'fifo_batch_info']);
            return [
                'id' => $o->id, 'code' => $o->code, 'funnel_id' => (int) $o->funnel_id, 'funnel_version_id' => (int) $o->funnel_version_id,
                'payment_type' => $o->payment_type, 'payment_status' => $o->payment_status, 'delivery_status' => $o->delivery_status,
                'grand_total' => (float) $o->grand_total, 'shipping_address' => json_decode($o->shipping_address, true), 'additional_info' => $o->additional_info,
                'lines' => $lines->map(fn ($l) => (array) $l)->all(),
            ];
        })->all();
        $out(['count' => count($orders), 'orders' => $orders]);

    case 'stock':
        $id = (int) ($argv[2] ?? 0);
        $out([
            'stock' => DB::table('product_stocks')->where('product_id', $id)->orderBy('id')->pluck('qty', 'variant')->all(),
            'batches' => (int) DB::table('purchase_products')->where('product_id', $id)->sum('remaining_qty'),
        ]);

    case 'set-stock':
        // Sets a stock row (and its batch) to an exact quantity, to stage "last unit" cases.
        $id = (int) ($argv[2] ?? 0);
        $variant = (string) ($argv[3] ?? '');
        $qty = (int) ($argv[4] ?? 0);
        DB::table('product_stocks')->where('product_id', $id)->where('variant', $variant)->update(['qty' => $qty]);
        DB::table('purchase_products')->where('product_id', $id)->where('variant', $variant)->update(['remaining_qty' => $qty, 'purchase_quantity' => (string) max($qty, 1)]);
        $out(['ok' => true]);

    case 'set-price':
        DB::table('products')->where('id', (int) $argv[2])->update(['unit_price' => (float) $argv[3]]);
        DB::table('product_stocks')->where('product_id', (int) $argv[2])->update(['price' => (float) $argv[3]]);
        $out(['ok' => true]);

    case 'cache-clear':
        // The test servers' private cache (settings, offers, rate limits).
        \Illuminate\Support\Facades\Cache::flush();
        $out(['ok' => true]);

    case 'product':
        // A fresh product (copy of the E2E mug) with stock and a purchase batch, so a test
        // can change it without disturbing others. product <name> <price> <qty> [<discount %>]
        $base = \App\Models\Product::where('name', 'E2E Travel Mug')->first() ?: $fail('Run "products" first.');
        $product = $base->replicate();
        $product->name = $argv[2] ?? 'E2E Fresh';
        $product->slug = 'e2e-fresh-' . substr(md5(uniqid('', true)), 0, 10);
        $product->unit_price = (float) ($argv[3] ?? 1000);
        $product->discount = (float) ($argv[5] ?? 0);
        $product->discount_type = 'percent';
        $product->variant_product = 0;
        // Its own category and no brand: the catalogue-search tests look for "E2E" products,
        // categories and brands, and these throw-away fixtures must not crowd them out.
        $fixtures = DB::table('categories')->where('slug', 'checkout-fixtures')->value('id');
        if (!$fixtures) {
            $category = new \App\Models\Category();
            $category->forceFill(['name' => 'Checkout Fixtures', 'slug' => 'checkout-fixtures', 'level' => 0])->save();
            $fixtures = $category->id;
        }
        $product->category_id = $fixtures;
        $product->brand_id = null;
        $qty = (int) ($argv[4] ?? 10);
        $product->current_stock = $qty;
        $product->save();
        DB::table('product_stocks')->insert(['product_id' => $product->id, 'variant' => '', 'sku' => 'FRESH-' . $product->id, 'price' => $product->unit_price, 'qty' => $qty, 'created_at' => now(), 'updated_at' => now()]);
        $purchase = DB::table('purchases')->insertGetId(['supplier_id' => 1, 'reference_no' => 'E2E-F' . $product->id, 'purchase_date' => date('Y-m-d'), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('purchase_products')->insert([
            'product_id' => $product->id, 'purchase_id' => $purchase, 'product_name' => $product->name, 'variant' => '', 'purchase_quantity' => (string) max($qty, 1),
            'remaining_qty' => $qty, 'buying_unit_cost' => '1000', 'total_cost' => (string) ($qty * 1000), 'product_tax' => '0', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $out(['id' => $product->id, 'slug' => $product->slug]);

    case 'templates':
        $out(app(\App\Services\Funnel\Templates\TemplateLibrary::class)->sync());

    case 'template-funnel':
        // template-funnel <template key> <bn|en> <title> [<product ids, comma separated>]
        // Creates a funnel from a template and puts the products into its slots, in order.
        $admin = User::where('email', 'e2e-admin@funnel.test')->first() ?: $fail('Run "users" first.');
        $template = \App\Models\FunnelTemplate::where('template_key', $argv[2] ?? '')->where('language', $argv[3] ?? 'bn')->first() ?: $fail('Unknown template (run "templates").');
        $ids = array_values(array_filter(array_map('intval', explode(',', (string) ($argv[5] ?? '')))));
        $choices = [];
        foreach (($template->product_slots['slots'] ?? []) as $i => $slot) {
            if (isset($ids[$i]) || isset($ids[0])) {
                $choices[$slot['key']] = ['product_id' => $ids[$i] ?? $ids[0]];
            }
        }
        $funnel = app(FunnelService::class)->createFromTemplate(['title' => $argv[4] ?? 'Template page'], $template, $ids ? $choices : [], $admin);
        $out(['id' => $funnel->id, 'slug' => $funnel->slug, 'revision' => $funnel->draft_revision]);

    case 'fill-for-publish':
        // Makes a template funnel publishable: a video link where one is needed, and
        // the sample review section removed.
        $admin = User::where('email', 'e2e-admin@funnel.test')->first() ?: $fail('Run "users" first.');
        $funnel = Funnel::find((int) ($argv[2] ?? 0)) ?: $fail('Unknown funnel.');
        $content = $funnel->draft_content;
        foreach ($content['page']['sections'] as $i => $section) {
            if (($section['type'] ?? '') === 'video_hero') {
                $content['page']['sections'][$i]['props']['url'] = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';
            }
            if (($section['children'][0]['type'] ?? '') === 'testimonial_cards') {
                unset($content['page']['sections'][$i]);
            }
        }
        $content['page']['sections'] = array_values($content['page']['sections']);
        [$funnel] = app(FunnelService::class)->saveDraft($funnel, $content, $funnel->draft_revision, $admin);
        $out(['revision' => $funnel->draft_revision]);

    case 'preview-link':
        // preview-link <funnel id> [<hours>] -> the secret link
        $admin = User::where('email', 'e2e-admin@funnel.test')->first() ?: $fail('Run "users" first.');
        $funnel = Funnel::find((int) ($argv[2] ?? 0)) ?: $fail('Unknown funnel.');
        [$link, $secret] = app(\App\Services\Funnel\FunnelPreviewService::class)->create($funnel, $admin, (int) ($argv[3] ?? 24));
        $out(['id' => $link->id, 'url' => config('funnel.public_url') . '/funnel/' . $funnel->slug . '/preview/' . $secret, 'secret' => $secret]);

    case 'revoke-link':
        $admin = User::where('email', 'e2e-admin@funnel.test')->first() ?: $fail('Run "users" first.');
        $funnel = Funnel::find((int) ($argv[2] ?? 0)) ?: $fail('Unknown funnel.');
        app(\App\Services\Funnel\FunnelPreviewService::class)->revoke($funnel, (int) ($argv[3] ?? 0), $admin);
        $out(['ok' => true]);

    case 'funnel-time':
        // funnel-time <funnel id> <scheduled_at|unpublish_at> <minutes from now, or "null">:
        // moves a schedule, to act out time passing without waiting.
        $funnel = Funnel::find((int) ($argv[2] ?? 0)) ?: $fail('Unknown funnel.');
        $column = in_array($argv[3] ?? '', ['scheduled_at', 'unpublish_at'], true) ? $argv[3] : $fail('Column must be scheduled_at or unpublish_at.');
        $funnel->forceFill([$column => ($argv[4] ?? 'null') === 'null' ? null : now()->addMinutes((int) $argv[4])])->save();
        $out(['status' => $funnel->status]);

    case 'reconcile':
        $out(['updated' => app(FunnelService::class)->reconcile()]);

    case 'noindex':
        $admin = User::where('email', 'e2e-admin@funnel.test')->first() ?: $fail('Run "users" first.');
        $funnel = Funnel::find((int) ($argv[2] ?? 0)) ?: $fail('Unknown funnel.');
        app(FunnelService::class)->updateDetails($funnel, ['noindex' => true], $admin);
        $out(['ok' => true]);

    case 'schedule':
        // schedule <funnel id> <minutes from now> [<end, minutes from now>]
        $admin = User::where('email', 'e2e-admin@funnel.test')->first() ?: $fail('Run "users" first.');
        $funnel = Funnel::find((int) ($argv[2] ?? 0)) ?: $fail('Unknown funnel.');
        $until = isset($argv[4]) ? now()->addMinutes((int) $argv[4]) : null;
        [$funnel] = app(FunnelService::class)->schedule($funnel, now()->addMinutes((int) ($argv[3] ?? 30)), $until, $admin);
        $out(['status' => $funnel->status]);

    case 'unpublish':
        $admin = User::where('email', 'e2e-admin@funnel.test')->first() ?: $fail('Run "users" first.');
        $funnel = Funnel::find((int) ($argv[2] ?? 0)) ?: $fail('Unknown funnel.');
        $out(['status' => app(FunnelService::class)->unpublish($funnel, $admin)->status]);

    case 'archive':
        $admin = User::where('email', 'e2e-admin@funnel.test')->first() ?: $fail('Run "users" first.');
        $funnel = Funnel::find((int) ($argv[2] ?? 0)) ?: $fail('Unknown funnel.');
        $out(['status' => app(FunnelService::class)->archive($funnel, $admin)->status]);

    case 'events':
        // Visitor events of one funnel, as the analytics dashboard counts them.
        $rows = DB::table('funnel_events')->where('funnel_id', (int) ($argv[2] ?? 0))->orderBy('id')->get();
        $out([
            'count' => $rows->count(),
            'types' => $rows->countBy('type')->all(),
            'visitors' => $rows->pluck('visitor_id')->unique()->values()->all(),
            'rows' => $rows->map(fn ($r) => (array) $r)->all(),
        ]);

    case 'attribution':
        // Campaign attribution of the orders of one funnel.
        $rows = DB::table('funnel_order_attributions')->where('funnel_id', (int) ($argv[2] ?? 0))->orderBy('id')->get();
        $out(['count' => $rows->count(), 'rows' => $rows->map(fn ($r) => (array) $r)->all()]);

    case 'receipt':
        // The receipt of the latest order of a funnel, as the payment gateway hands it back.
        $order = DB::table('orders')->where('funnel_id', (int) ($argv[2] ?? 0))->orderByDesc('id')->first() ?: $fail('No order.');
        $out(['receipt' => \App\Services\Funnel\Checkout\Receipt::make((int) $order->combined_order_id, (int) $order->funnel_id), 'code' => $order->code]);

    case 'product-orders':
        // Orders of any kind (website, POS, funnel) that contain a product: product-orders <product id>
        $ids = DB::table('order_details')->where('product_id', (int) ($argv[2] ?? 0))->pluck('order_id');
        $orders = DB::table('orders')->whereIn('id', $ids)->orderBy('id')->get()->map(fn ($o) => [
            'id' => $o->id, 'code' => $o->code, 'funnel_id' => $o->funnel_id ?? null, 'payment_type' => $o->payment_type,
            'delivery_status' => $o->delivery_status, 'grand_total' => (float) $o->grand_total,
        ])->all();
        $out(['count' => count($orders), 'orders' => $orders]);

    case 'test-token':
        $out(['token' => \App\Services\Funnel\Analytics\TestOrderToken::make((int) ($argv[2] ?? 0))]);

    case 'saved-sections-clear':
        DB::table('funnel_saved_sections')->delete();
        $out(['ok' => true]);

    case 'registry':
        // The block registry as the editor receives it, for the frontend's
        // unit test fixture.
        $out(app(\App\Services\Funnel\BlockRegistry::class)->toArray());

    case 'touch':
        $admin = User::where('email', 'e2e-admin@funnel.test')->first() ?: $fail('Run "users" first.');
        $funnel = Funnel::find((int) ($argv[2] ?? 0)) ?: $fail('Unknown funnel.');
        $content = $funnel->draft_content;
        $content['page']['sections'][] = [
            'id' => 'other_tab_' . substr(md5((string) microtime(true)), 0, 6),
            'type' => 'section',
            'children' => [['id' => 'other_tab_text_' . substr(md5((string) mt_rand()), 0, 6), 'type' => 'text', 'props' => ['text' => 'Saved from another window']]],
        ];
        [$funnel] = app(FunnelService::class)->saveDraft($funnel, $content, $funnel->draft_revision, $admin);
        $out(['revision' => $funnel->draft_revision]);
}

$fail('Unknown command. See the comment at the top of this file.');
