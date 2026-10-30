<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ProductStockExport implements FromCollection, WithHeadings
{
    /**
    * @return \Illuminate\Support\Collection
    */

    protected $products;

    public function __construct($products)
    {
        $this->products = $products;
    }


    public function collection()
    {

        return $this->products->map(function ($product) {
            $qty = 0;
            foreach ($product->stocks as $key => $stock) {
                $qty += $stock->qty;
            }

            return [
                'name' => $product->name,
                'stock' => $qty,
            ];
        });
        
    }

    

    public function headings(): array
    {
        return [
            'Product Name',
            'Stock'
        ];
    }
}
