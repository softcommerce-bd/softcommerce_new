<?php

namespace App\Http\Controllers\Api\V2;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Likes on shoppable (reel) videos. A visitor is the random key their browser keeps, so guests can
 * like too; one like per visitor and product, and a second tap takes it back.
 */
class VideoLikeController extends Controller
{
    private const TABLE = 'product_video_likes';

    public static function ready(): bool
    {
        static $ready = null;

        return $ready ??= Schema::hasTable(self::TABLE);
    }

    /** @return array<int,int> product id => like count */
    public static function counts(array $productIds): array
    {
        if (!self::ready() || $productIds === []) {
            return [];
        }

        return DB::table(self::TABLE)->whereIn('product_id', $productIds)->groupBy('product_id')
            ->selectRaw('product_id, count(*) as aggregate')->pluck('aggregate', 'product_id')->map(fn ($n) => (int) $n)->all();
    }

    /** @return array<int,bool> product id => this visitor liked it */
    public static function likedBy(?string $visitorKey, array $productIds): array
    {
        if (!self::ready() || !self::validKey($visitorKey) || $productIds === []) {
            return [];
        }

        return DB::table(self::TABLE)->where('visitor_key', $visitorKey)->whereIn('product_id', $productIds)
            ->pluck('product_id')->mapWithKeys(fn ($id) => [(int) $id => true])->all();
    }

    private static function validKey(?string $key): bool
    {
        return is_string($key) && preg_match('/^[A-Za-z0-9_-]{16,64}$/', $key) === 1;
    }

    public function toggle(Request $request)
    {
        if (!self::ready()) {
            return response()->json(['success' => false, 'message' => 'Likes are not set up yet.']);
        }

        $productId = (int) $request->product_id;
        $key = (string) $request->visitor_key;
        if (!self::validKey($key) || $productId < 1) {
            return response()->json(['success' => false, 'message' => 'Invalid request.'], 422);
        }
        if (!Product::where('id', $productId)->whereNotNull('custom_video')->exists()) {
            return response()->json(['success' => false, 'message' => 'Video not found.'], 404);
        }

        $row = ['product_id' => $productId, 'visitor_key' => $key];
        $existing = DB::table(self::TABLE)->where($row);
        if ($existing->exists()) {
            $existing->delete();
            $liked = false;
        } else {
            // A double tap can race itself; the unique key makes the second insert a no-op.
            DB::table(self::TABLE)->insertOrIgnore($row + ['created_at' => now()]);
            $liked = true;
        }

        return response()->json([
            'success' => true,
            'liked' => $liked,
            'count' => (int) DB::table(self::TABLE)->where('product_id', $productId)->count(),
        ]);
    }
}
