<?php

namespace App\Providers;

use App\Models\Category;
use Illuminate\Support\ServiceProvider;

class CategoryServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
       // Share main categories with their subcategories with all views
       view()->share('categories', $this->getMainCategoriesWithSubcategories());
    }

    protected function getMainCategoriesWithSubcategories()
    {
        // Fetch main categories where parent_id is 0 and load their subcategories
        return Category::where('parent_id', 0)
            ->with('children') // Load subcategories
            ->get();
    }
}
