<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class InhouseSaleProductExport implements FromCollection,WithHeadings
{

    protected $products;

    public function __construct($products)
    {
        $this->products = $products;
    }


    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        return $this->products->map(function ($product) {
            return [
                'name' => $product->name,
                'num_of_sale' => $product->num_of_sale,
            ];
        });
        
    }

    public function headings(): array
    {
        return [
            'Product Name',
            'Number of Sales'
        ];
    }


}
