<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
use App\Models\Category;
use App\Models\Product;

class AppServiceProvider extends ServiceProvider
{
  /**
   * Bootstrap any application services.
   *
   * @return void
   */
  public function boot()
  {
    \App\Models\Order::observe(\App\Observers\OrderStockObserver::class);
    Schema::defaultStringLength(191);
    Paginator::useBootstrap();
  
  }
  /**
   * Register any application services.
   *
   * @return void
   */
  public function register()
  {
    //
  }
}
