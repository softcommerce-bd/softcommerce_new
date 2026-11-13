<?php

namespace Tests\Feature\Funnel;

use Illuminate\Support\Facades\DB;
use Tests\Funnel\BuildsLiveFunnel;
use Tests\Inventory\InventoryTestCase;

/** Reel likes: one per visitor, a second tap takes it back, counts come with the video list. */
class VideoLikesTest extends InventoryTestCase
{
    use BuildsLiveFunnel;

    public function test_likes_are_counted_once_per_visitor_and_listed_with_the_videos(): void
    {
        $this->setUpLiveFunnel();
        // The table comes from sqlupdates/video_likes_v1.sql, run once on the test database
        // (creating it here would end the test's transaction).
        if (!\Illuminate\Support\Facades\Schema::hasTable('product_video_likes')) {
            $this->markTestSkipped('Run sqlupdates/video_likes_v1.sql on the test database first.');
        }
        DB::table('product_video_likes')->delete();

        [, $product] = $this->liveFunnel(['M' => 5], ['cash_on_delivery']);
        DB::table('products')->where('id', $product->id)->update(['custom_video' => 'a.mp4']);

        $a = 'visitor-aaaaaaaaaaaaaaaa';
        $b = 'visitor-bbbbbbbbbbbbbbbb';

        $this->postJson('/api/v2/video-likes/toggle', ['product_id' => $product->id, 'visitor_key' => $a])
            ->assertOk()->assertJsonPath('liked', true)->assertJsonPath('count', 1);
        $this->postJson('/api/v2/video-likes/toggle', ['product_id' => $product->id, 'visitor_key' => $b])
            ->assertJsonPath('count', 2);

        // The list carries the count and whether this visitor liked it.
        $row = collect($this->getJson('/api/v2/buy-watch-products?visitor_key=' . $a)->assertOk()->json('data'))->firstWhere('id', $product->id);
        $this->assertSame(2, $row['likes_count']);
        $this->assertTrue($row['liked']);
        $other = collect($this->getJson('/api/v2/buy-watch-products?visitor_key=visitor-cccccccccccccccc')->json('data'))->firstWhere('id', $product->id);
        $this->assertFalse($other['liked']);

        // A second tap takes the like back; junk keys and unknown products are refused.
        $this->postJson('/api/v2/video-likes/toggle', ['product_id' => $product->id, 'visitor_key' => $a])
            ->assertJsonPath('liked', false)->assertJsonPath('count', 1);
        $this->postJson('/api/v2/video-likes/toggle', ['product_id' => $product->id, 'visitor_key' => 'x'])->assertStatus(422);
        $this->postJson('/api/v2/video-likes/toggle', ['product_id' => 999999, 'visitor_key' => $a])->assertStatus(404);
    }
}
