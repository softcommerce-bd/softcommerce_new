<?php

namespace Tests\Feature\Funnel;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Funnel;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Upload;
use App\Models\User;
use App\Services\Funnel\EditorSession;
use App\Services\Funnel\FunnelService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Funnel\FunnelTestCase;

class FunnelEditorMediaAndCatalogueTest extends FunnelTestCase
{
    private const BASE = '/api/v2/funnel-editor';

    private function tokenFor(User $user): string
    {
        $funnel = app(FunnelService::class)->create(['title' => 'Media funnel'], $this->makeAdmin());
        $code = app(EditorSession::class)->issueHandoff($user, $funnel);
        $token = $this->postJson(self::BASE . '/session', ['code' => $code])->assertOk()->json('token');
        $this->forgetAuth();

        return $token;
    }

    private function api(string $token)
    {
        $this->forgetAuth();

        return $this->withHeaders(['Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json']);
    }

    private function product(array $attributes, array $stocks): Product
    {
        $product = new Product();
        $product->forceFill(array_merge([
            'name' => 'Product',
            'added_by' => 'admin',
            'user_id' => 1,
            'unit_price' => 1000,
            'published' => 1,
            'approved' => 1,
            'digital' => 0,
            'auction_product' => 0,
            'variant_product' => count($stocks) > 1 ? 1 : 0,
            'discount' => 0,
            'discount_type' => 'amount',
            'slug' => 'product-' . uniqid(),
        ], $attributes))->save();

        foreach ($stocks as $stock) {
            ProductStock::create(array_merge(['product_id' => $product->id, 'variant' => '', 'price' => $product->unit_price, 'qty' => 5], $stock));
        }

        return $product;
    }

    public function test_upload_stores_a_real_image_in_the_shared_media_library()
    {
        Storage::fake('local');
        $token = $this->tokenFor($this->makeAdmin());

        $response = $this->api($token)->post(self::BASE . '/media', [
            'file' => UploadedFile::fake()->image('blender-hero.png', 2400, 1200),
        ])->assertStatus(201);

        $upload = Upload::find($response->json('data.id'));
        $this->assertNotNull($upload);
        $this->assertSame('image', $upload->type);
        $this->assertSame('png', $upload->extension);
        $this->assertSame('blender-hero', $upload->file_original_name);
        Storage::disk('local')->assertExists($upload->file_name);

        // Large photos are scaled down like the admin uploader does.
        [$width] = getimagesize(Storage::disk('local')->path($upload->file_name));
        $this->assertSame(1500, $width);

        $this->api($token)->getJson(self::BASE . '/media?q=blender')
            ->assertOk()
            ->assertJsonPath('data.0.id', $upload->id);
    }

    public function test_upload_rejects_files_that_are_not_raster_images()
    {
        Storage::fake('local');
        $token = $this->tokenFor($this->makeAdmin());

        $fakePng = UploadedFile::fake()->createWithContent('not-really.png', '<?php echo "hi";');
        $this->api($token)->post(self::BASE . '/media', ['file' => $fakePng])
            ->assertStatus(422)->assertJsonPath('code', 'invalid_image');

        $svg = UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $this->api($token)->post(self::BASE . '/media', ['file' => $svg])
            ->assertStatus(422)->assertJsonPath('code', 'invalid_image');

        config(['funnel.media.max_kb' => 1]);
        $this->api($token)->post(self::BASE . '/media', ['file' => UploadedFile::fake()->image('big.jpg', 800, 800)->size(50)])
            ->assertStatus(422)->assertJsonPath('code', 'image_too_large');

        $this->api($token)->post(self::BASE . '/media', [])->assertStatus(422)->assertJsonPath('code', 'missing_file');
        $this->assertSame(0, Upload::count());
    }

    public function test_media_and_products_need_edit_permission()
    {
        $viewer = $this->makeUser('staff', ['view_funnels']);
        $token = $this->tokenFor($viewer);

        $this->api($token)->getJson(self::BASE . '/media')->assertStatus(403);
        $this->api($token)->getJson(self::BASE . '/products')->assertStatus(403);
    }

    public function test_product_search_reads_the_existing_catalogue()
    {
        $category = new Category();
        $category->forceFill(['name' => 'Kitchen Appliances', 'slug' => 'kitchen-' . uniqid()])->save();
        $brand = Brand::create(['name' => 'Walton', 'slug' => 'walton-' . uniqid()]);

        $blender = $this->product([
            'name' => 'Super Blender 1000W',
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'unit_price' => 4000,
            'discount' => 10,
            'discount_type' => 'percent',
        ], [['sku' => 'BLD-1000', 'price' => 4000, 'qty' => 7]]);

        $shirt = $this->product(['name' => 'Cotton Shirt', 'unit_price' => 800], [
            ['variant' => 'Red-M', 'sku' => 'SH-RM', 'price' => 800, 'qty' => 0],
            ['variant' => 'Red-L', 'sku' => 'SH-RL', 'price' => 900, 'qty' => 3],
        ]);

        $hidden = $this->product(['name' => 'Blender Prototype', 'published' => 0], [['sku' => 'BLD-X', 'qty' => 1]]);
        $this->product(['name' => 'Digital Blender Manual', 'digital' => 1], [['sku' => 'BLD-PDF']]);

        $token = $this->tokenFor($this->makeAdmin());

        $byName = $this->api($token)->getJson(self::BASE . '/products?q=blender')->assertOk();
        $this->assertSame([$blender->id], array_column($byName->json('data'), 'id'));
        $item = $byName->json('data.0');
        $this->assertSame('BLD-1000', $item['sku']);
        $this->assertSame('Kitchen Appliances', $item['category']);
        $this->assertSame('Walton', $item['brand']);
        $this->assertSame(7, $item['stock']['quantity']);
        $this->assertEquals(4000, $item['price']['regular_min']);
        $this->assertEquals(3600, $item['price']['sale_min']);

        foreach (['BLD-1000', 'walton', 'kitchen', (string) $blender->id] as $term) {
            $ids = array_column($this->api($token)->getJson(self::BASE . '/products?q=' . urlencode($term))->json('data'), 'id');
            $this->assertContains($blender->id, $ids, $term);
        }

        $all = $this->api($token)->getJson(self::BASE . '/products?q=blender&status=all')->json('data');
        $this->assertEqualsCanonicalizing([$blender->id, $hidden->id], array_column($all, 'id'));

        $variants = $this->api($token)->getJson(self::BASE . '/products?q=shirt')->json('data.0');
        $this->assertTrue($variants['has_variants']);
        $this->assertSame(['Red-M', 'Red-L'], array_column($variants['variants'], 'variant'));
        $this->assertSame(3, $variants['stock']['quantity']);

        // Bound products are looked up by id whatever their status.
        $bound = $this->api($token)->getJson(self::BASE . '/products?ids=' . $hidden->id . ',' . $blender->id)->json('data');
        $this->assertEqualsCanonicalizing([$blender->id, $hidden->id], array_column($bound, 'id'));
        $this->assertFalse(collect($bound)->firstWhere('id', $hidden->id)['published']);

        $inStock = array_column($this->api($token)->getJson(self::BASE . '/products?in_stock=1&q=shirt')->json('data'), 'id');
        $this->assertSame([$shirt->id], $inStock);
    }

    public function test_orders_carry_nullable_funnel_attribution()
    {
        $this->assertTrue(Schema::hasColumns('orders', ['funnel_id', 'funnel_version_id']));

        $admin = $this->makeAdmin();
        $service = app(FunnelService::class);
        $funnel = $service->create(['title' => 'Attribution'], $admin);
        [$funnel] = $service->saveDraft($funnel, $this->sellingPage(), 1, $admin);
        [$funnel, $version] = $service->publish($funnel, $admin);

        $plain = DB::table('orders')->insertGetId(['grand_total' => 100, 'order_from' => 'web']);
        $attributed = DB::table('orders')->insertGetId([
            'grand_total' => 200,
            'order_from' => 'funnel',
            'funnel_id' => $funnel->id,
            'funnel_version_id' => $version->id,
        ]);

        $this->assertNull(DB::table('orders')->where('id', $plain)->value('funnel_id'));

        // If a funnel row were ever hard-deleted, its orders stay and lose
        // only the reference.
        Funnel::withTrashed()->find($funnel->id)->forceDelete();
        $order = DB::table('orders')->where('id', $attributed)->first();
        $this->assertNotNull($order);
        $this->assertNull($order->funnel_id);
        $this->assertNull($order->funnel_version_id);
        $this->assertEquals(200, $order->grand_total);
    }
}
