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
    \Illuminate\Support\Facades\Event::listen(\Illuminate\Database\Events\TransactionBeginning::class, fn () => \App\Services\Accounting\LegacyLedgerBridge::began());
    \Illuminate\Support\Facades\Event::listen(\Illuminate\Database\Events\TransactionCommitted::class, fn () => \App\Services\Accounting\LegacyLedgerBridge::committed());
    \Illuminate\Support\Facades\Event::listen(\Illuminate\Database\Events\TransactionRolledBack::class, fn () => \App\Services\Accounting\LegacyLedgerBridge::rolledBack());
    // balance changes made outside any transaction are journalled when the request / command / job ends
    $this->app->terminating(fn () => \App\Services\Accounting\LegacyLedgerBridge::flush());
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
