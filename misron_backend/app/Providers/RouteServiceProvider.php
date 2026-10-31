<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class RouteServiceProvider extends ServiceProvider
{
  /**
   * This namespace is applied to your controller routes.
   *
   * In addition, it is set as the URL generator's root namespace.
   *
   * @var string
   */
   protected $namespace = null;

  /**
   * Define your route model bindings, pattern filters, etc.
   *
   * @return void
   */
  public function boot()
  {
    //

    parent::boot();

    $this->configureRateLimiting();

    // bounded deadlock retry on the state-changing inventory / accounting routes (config/deadlock_retry.php); after the routes exist
    $this->app->booted(fn () => \App\Support\DeadlockRetryRoutes::attach($this->app['router']));
  }

  /**
   * Define the routes for the application.
   *
   * @return void
   */
  public function map()
  {
     $this->mapApiRoutes();

     $this->mapApiSellerRoutes();
    
     $this->mapAdminRoutes();

     $this->mapSellerRoutes();
    
     $this->mapAffiliateRoutes();
    
     $this->mapRefundRoutes();
    
     $this->mapClubPointsRoutes();
    
     $this->mapOtpRoutes();
    
     $this->mapOfflinePaymentRoutes();
    
     $this->mapAfricanPaymentGatewayRoutes();
    
     $this->mapPaytmRoutes();
    
     $this->mapPosRoutes();
    
     $this->mapSellerPackageRoutes();
    
     $this->mapDeliveryBoyRoutes();
    
     $this->mapAuctionRoutes();

     $this->mapWholesaleRoutes();
    
     $this->mapWebRoutes();

    // $this->mapInstallRoutes();

    //$this->mapUpdateRoutes();
  }

  /**
   * Define the "b2b" routes for the application.
   *
   * These routes all receive session state, CSRF protection, etc.
   *
   * @return void
   */
  protected function mapWholesaleRoutes()
  {
    Route::middleware('web')
       ->namespace($this->namespace)
       ->group(base_path('routes/wholesale.php'));
  }

  /**
   * Define the "delivery boy" routes for the application.
   *
   * These routes all receive session state, CSRF protection, etc.
   *
   * @return void
   */
  protected function mapDeliveryBoyRoutes()
  {
    Route::middleware('web')
       ->namespace($this->namespace)
       ->group(base_path('routes/delivery_boy.php'));
  }

    /**
   * Define the "auction" routes for the application.
   *
   * These routes all receive session state, CSRF protection, etc.
   *
   * @return void
   */
  protected function mapAuctionRoutes()
  {
    Route::middleware('web')
       ->namespace($this->namespace)
       ->group(base_path('routes/auction.php'));
  }

  /**
   * Define the "seller package" routes for the application.
   *
   * These routes all receive session state, CSRF protection, etc.
   *
   * @return void
   */
  protected function mapSellerPackageRoutes()
  {
    Route::middleware('web')
       ->namespace($this->namespace)
       ->group(base_path('routes/seller_package.php'));
  }

  /**
   * Define the "affiliate" routes for the application.
   *
   * These routes all receive session state, CSRF protection, etc.
   *
   * @return void
   */
  protected function mapAffiliateRoutes()
  {
    Route::middleware('web')
       ->namespace($this->namespace)
       ->group(base_path('routes/affiliate.php'));
  }

  /**
   * Define the "offline payment" routes for the application.
   *
   * These routes all receive session state, CSRF protection, etc.
   *
   * @return void
   */
  protected function mapOfflinePaymentRoutes()
  {
    Route::middleware('web')
       ->namespace($this->namespace)
       ->group(base_path('routes/offline_payment.php'));
  }


  /**
   * Define the "Asian payment" routes for the application.
   *
   * These routes all receive session state, CSRF protection, etc.
   *
   * @return void
   */
  protected function mapPaytmRoutes()
  {
    Route::middleware('web')
       ->namespace($this->namespace)
       ->group(base_path('routes/paytm.php'));
  }

  /**
   * Define the "African payment" routes for the application.
   *
   * These routes all receive session state, CSRF protection, etc.
   *
   * @return void
   */
  protected function mapAfricanPaymentGatewayRoutes()
  {
    Route::middleware('web')
       ->namespace($this->namespace)
       ->group(base_path('routes/african_pg.php'));
  }

  /**
   * Define the "refund" routes for the application.
   *
   * These routes all receive session state, CSRF protection, etc.
   *
   * @return void
   */
  protected function mapRefundRoutes()
  {
    Route::middleware('web')
       ->namespace($this->namespace)
       ->group(base_path('routes/refund_request.php'));
  }

  /**
   * Define the "club points" routes for the application.
   *
   * These routes all receive session state, CSRF protection, etc.
   *
   * @return void
   */
  protected function mapClubPointsRoutes()
  {
    Route::middleware('web')
       ->namespace($this->namespace)
       ->group(base_path('routes/club_points.php'));
  }

  /**
   * Define the "OTP System" routes for the application.
   *
   * These routes all receive session state, CSRF protection, etc.
   *
   * @return void
   */
  protected function mapOtpRoutes()
  {
    Route::middleware('web')
       ->namespace($this->namespace)
       ->group(base_path('routes/otp.php'));
  }

  /**
   * Define the "POS System" routes for the application.
   *
   * These routes all receive session state, CSRF protection, etc.
   *
   * @return void
   */
  protected function mapPosRoutes()
  {
    Route::middleware('web')
       ->namespace($this->namespace)
       ->group(base_path('routes/pos.php'));
  }

  /**
   * Define the "updating" routes for the application.
   *
   * These routes all receive session state, CSRF protection, etc.
   *
   * @return void
   */
  protected function mapUpdateRoutes()
  {
    Route::middleware('web')
       ->namespace($this->namespace)
       ->group(base_path('routes/update.php'));
  }

  /**
   * Define the "installation" routes for the application.
   *
   * These routes all receive session state, CSRF protection, etc.
   *
   * @return void
   */
  protected function mapInstallRoutes()
  {
    Route::middleware('web')
       ->namespace($this->namespace)
       ->group(base_path('routes/install.php'));
  }

  /**
   * Define the "web" routes for the application.
   *
   * These routes all receive session state, CSRF protection, etc.
   *
   * @return void
   */
  protected function mapWebRoutes()
  {
    Route::middleware('web')
       ->namespace($this->namespace)
       ->group(base_path('routes/web.php'));
  }

  /**
   * Define the "admin" routes for the application.
   *
   * These routes all receive session state, CSRF protection, etc.
   *
   * @return void
   */
  protected function mapAdminRoutes()
  {
    Route::middleware('web')
       ->namespace($this->namespace)
       ->group(base_path('routes/admin.php'));
  }

  /**
   * Define the "seller" routes for the application.
   *
   * These routes all receive session state, CSRF protection, etc.
   *
   * @return void
   */
  protected function mapSellerRoutes()
  {
    Route::middleware('web')
       ->namespace($this->namespace)
       ->group(base_path('routes/seller.php'));
  }

  /**
   * Define the "api" routes for the application.
   *
   * These routes are typically stateless.
   *
   * @return void
   */
  protected function mapApiSellerRoutes()
  {
    Route::prefix('api')
       ->middleware('api')
       ->namespace($this->namespace)
       ->group(base_path('routes/api_seller.php'));
  }

  /**
   * Define the "api" routes for the application.
   *
   * These routes are typically stateless.
   *
   * @return void
   */
  protected function mapApiRoutes()
  {
    Route::prefix('api')
       ->middleware('api')
       ->namespace($this->namespace)
       ->group(base_path('routes/api.php'));
  }

  /**
     * Configure the rate limiters for the application.
     *
     * @return void
     */
    protected function configureRateLimiting()
    {
        // Customer-facing funnel routes. Each has its own counter: the plain "throttle:N,M"
        // middleware keys every route by client address alone, so a page that polls prices
        // would use up the allowance meant for placing the order.
        foreach (['funnel-page' => 240, 'funnel-offers' => 120, 'funnel-quote' => 240, 'funnel-config' => 120, 'funnel-checkout' => 20, 'funnel-order' => 60, 'funnel-pay' => 20, 'funnel-preview' => 180, 'funnel-events' => 120] as $name => $perMinute) {
            RateLimiter::for($name, function (Request $request) use ($perMinute) {
                return Limit::perMinute($perMinute)->by($request->ip());
            });
        }

        // Opening the editor (exchanging the one-time code from the admin panel). Ten a minute per
        // address unless configured; the browser test suite opens the editor far more often.
        RateLimiter::for('funnel-editor-session', function (Request $request) {
            return Limit::perMinute(max(1, (int) config('funnel.editor_session.exchanges_per_minute', 10)))->by($request->ip());
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(600)->by(optional($request->user())->id ?: $request->ip());
        });
    }

}
