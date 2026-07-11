<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Cache;
use DB;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    public function boot()
    {
        /*if( (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443) {
            URL::forceScheme('https');
        }*/
        //setting language
        if(isset($_COOKIE['language'])) {
            \App::setLocale($_COOKIE['language']);
        } else {
            \App::setLocale('en');
        }
        Schema::defaultStringLength(191);

        // Guard against boot before the database is installed/migrated (fresh install, CLI, migrations).
        if (! $this->settingsAvailable()) {
            View::share('general_setting', null);
            View::share('currency', '');
            View::share('alert_product', 0);
            return;
        }

        //get general setting value
        $general_setting = DB::table('general_settings')->latest()->first();
        $currency = $general_setting ? (\App\Currency::find($general_setting->currency) ?? '') : '';
        View::share('general_setting', $general_setting);
        View::share('currency', $currency);
        if ($general_setting) {
            config([
                'staff_access' => $general_setting->staff_access,
                'date_format' => $general_setting->date_format,
                'currency' => $currency ? $currency->code : null,
                'currency_position' => $general_setting->currency_position,
            ]);
        }

        $alert_product = DB::table('products')->where('is_active', true)->whereColumn('alert_quantity', '>', 'qty')->count();
        View::share('alert_product', $alert_product);

        View::composer('frontend.layout.main', function ($view) {
            $categories = Cache::remember('frontend_nav_categories', 3600, function () {
                return \App\Category::where('is_active', true)->orderBy('name')->get(['id', 'name']);
            });
            $view->with('categories', $categories);
        });
    }

    /**
     * Whether the core settings table exists and can be queried.
     * Prevents boot-time crashes on a fresh (unmigrated) database.
     */
    private function settingsAvailable()
    {
        try {
            return Schema::hasTable('general_settings');
        } catch (\Throwable $e) {
            return false;
        }
    }
}
