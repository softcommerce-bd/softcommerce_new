<?php

namespace App\Services;

use AizPackages\CombinationGenerate\Services\CombinationService;
use App\Models\ProductStock;
use App\Utility\ProductUtility;
use Illuminate\Support\Facades\Log;

class ProductStockService
{
    
    
    // public function store(array $data, $product)
    // {
    //     $collection = collect($data);
    
    //     // Get attribute options (if any)
    //     $options = ProductUtility::get_attribute_options($collection);
        
    //     // Generate combinations based on options
    //     $combinations = (new CombinationService())->generate_combination($options);
    
    //     $totalStock = 0; // Track total stock for updating the product table
    
    //     $variant = '';
        
    //     // Handling product variants
    //     if (count($combinations) > 0) {
    //         $product->variant_product = 1;
    //         $product->save();
    
    //         foreach ($combinations as $combination) {
    //             $str = ProductUtility::get_combination_string($combination, $collection);
    
    //             // Fetching variant-specific data from the request
    //             $price = request()['price_' . str_replace('.', '_', $str)];
    //             $sku = request()['sku_' . str_replace('.', '_', $str)];
    //             $qty = request()['qty_' . str_replace('.', '_', $str)];
    //             $image = request()['img_' . str_replace('.', '_', $str)];
    
    //             // Creating ProductStock entry for each combination
    //             ProductStock::create([
    //                 'product_id' => $product->id,
    //                 'variant' => $str,
    //                 'price' => $price,
    //                 'sku' => $sku,
    //                 'qty' => $qty,
    //                 'image' => $image,
    //             ]);
    
    //             // Add the qty for each variant to the total stock
    //             $totalStock += $qty;
    //         }
    //     } else {
    //         // Handling non-variant products
    
    //         // Do NOT unset current_stock; instead, use it in the stock creation
    //         $qty = $collection['current_stock'] ?? 0;  // Ensure current_stock is set correctly
    //         $price = $collection['unit_price'] ?? 0;
    
    //         // Removing unused keys from the collection
    //         $collection->forget(['colors_active', 'colors', 'choice_no']);
    
    //         // Merge required data
    //         $data = $collection->merge(compact('variant', 'qty', 'price'))->toArray();
            
    //         // Create the ProductStock record for non-variant product
    //         ProductStock::create($data);
    
    //         // Set total stock for non-variant product
    //         $totalStock = $qty;
    //     }
    
    //     // Update the products table with the total stock for the product
    //     $product->current_stock = $totalStock;
    //     $product->save();
    // }
public function store(array $data, $product)
{
    $collection = collect($data);

    // Get ALL request data (including from files)
    $allRequestData = request()->all();
    
    // Get attribute options (if any)
    $options = ProductUtility::get_attribute_options($collection);
    
    // Generate combinations based on options
    $combinations = (new CombinationService())->generate_combination($options);

    $totalStock = 0;
    $variant = '';
    
    // Handling product variants
    if (count($combinations) > 0) {
        $product->variant_product = 1;
        $product->save();

        foreach ($combinations as $combination) {
            $str = ProductUtility::get_combination_string($combination, $collection);
            $variantKey = str_replace('.', '_', $str);
            
            // Try multiple sources for variant data
            $price = $collection->get('price_' . $variantKey, 
                     $allRequestData['price_' . $variantKey] ?? 
                     $collection->get('unit_price', 0));
                     
            $sku = $collection->get('sku_' . $variantKey, 
                   $allRequestData['sku_' . $variantKey] ?? null);
                   
            $qty = $collection->get('qty_' . $variantKey, 
                   $allRequestData['qty_' . $variantKey] ?? 0);
                   
            $image = $collection->get('img_' . $variantKey, 
                     $allRequestData['img_' . $variantKey] ?? null);

            \Log::info('Creating stock for variant: ' . $str, [
                'price' => $price,
                'qty' => $qty,
                'sku' => $sku
            ]);

            ProductStock::create([
                'product_id' => $product->id,
                'variant' => $str,
                'price' => $price,
                'sku' => $sku,
                'qty' => $qty,
                'image' => $image,
            ]);

            $totalStock += (int)$qty;
        }
    } else {
        // Non-variant products
        $qty = $collection->get('current_stock', 0);
        $price = $collection->get('unit_price', 0);
        $sku = $collection->get('sku', null);

        ProductStock::create([
            'product_id' => $product->id,
            'variant' => '',
            'price' => $price,
            'sku' => $sku,
            'qty' => $qty,
            'image' => null,
        ]);

        $totalStock = $qty;
    }

    $product->current_stock = $totalStock;
    $product->save();
    
    return $product;
}

    public function product_duplicate_store($product_stocks , $product_new)
    {
        foreach ($product_stocks as $key => $stock) {
            $product_stock              = new ProductStock;
            $product_stock->product_id  = $product_new->id;
            $product_stock->variant     = $stock->variant;
            $product_stock->price       = $stock->price;
            $product_stock->sku         = $stock->sku;
            $product_stock->qty         = $stock->qty;
            $product_stock->save();
        }
    }
}
