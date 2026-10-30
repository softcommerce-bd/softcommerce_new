<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Resources\V2\CategoryCollection;
use App\Models\Category;

class SubCategoryController extends Controller
{
    public function index($id)
    {
        // Same display order as every other category list; see
        // Category::scopeInDisplayOrder(). This used to return rows in primary
        // key order, so the admin's ranking had no effect on submenus.
        return new CategoryCollection(
            Category::where('parent_id', $id)->inDisplayOrder()->get()
        );
    }
}
