<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use App;

class Category extends Model
{


    protected $with = ['category_translations'];

    /**
     * The single display order used everywhere a category list is shown:
     * the featured section on the home page, the menu panel and the footer.
     *
     * Four keys, in order:
     *
     *   level ASC        parents before their children, so a flat list still
     *                    reads as "all the top-level ones, then the rest".
     *   ranked first     `order_level` is the admin's manual priority and is 0
     *                    when they have not set one. 0 must sort last rather
     *                    than first, so rows with a value are grouped ahead of
     *                    rows without.
     *   order_level ASC  1 before 2 before 3 within the ranked group.
     *   created_at DESC  the unranked remainder, newest first.
     *
     * `order_level` ranks a row against every other row at its own level, not
     * only against its siblings: the featured grid is one flat run of tiles, so
     * two children under different parents still need a defined order between
     * them. Nesting the same sorted rows under their parent gives the correct
     * per-parent order for the menu and footer for free.
     */
    public function scopeInDisplayOrder(Builder $query)
    {
        return $query
            ->orderBy('level')
            ->orderByRaw('(order_level = 0) asc')
            ->orderBy('order_level')
            ->orderBy('created_at', 'desc');
    }

    public function getTranslation($field = '', $lang = false){
        $lang = $lang == false ? App::getLocale() : $lang;
        $category_translation = $this->category_translations->where('lang', $lang)->first();
        return $category_translation != null ? $category_translation->$field : $this->$field;
    }

    public function category_translations(){
    	return $this->hasMany(CategoryTranslation::class);
    }

    public function coverImage(){
    	return $this->belongsTo(Upload::class, 'cover_image');
    }

    public function catIcon(){
    	return $this->belongsTo(Upload::class, 'icon');
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_categories');
    }
    
    public function bannerImage(){
    	return $this->belongsTo(Upload::class, 'banner');
    }
     public function headerBannerImage(){
    	return $this->belongsTo(Upload::class, 'header_banner');
    }

    public function classified_products(){
    	return $this->hasMany(CustomerProduct::class);
    }

    public function categories()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function childrenCategories()
    {
        return $this->hasMany(Category::class, 'parent_id')->with('categories');
    }

    public function parentCategory()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function attributes()
    {
        return $this->belongsToMany(Attribute::class);
    }

    /**
     * The size chart owned by this category.
     *
     * The foreign key lives on `size_charts.category_id`, so this is a hasOne.
     * It was previously written as a belongsTo with the keys reversed, which
     * resolved by coincidence but broke under eager loading.
     *
     * A category holds at most one chart: SizeChartRequest enforces a unique
     * rule on `category_id`.
     */
    public function sizeChart()
    {
        return $this->hasOne(SizeChart::class, 'category_id');
    }

    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    /**
     * Get the parent category.
     */
    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

}
