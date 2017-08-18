<?php

namespace Thorazine\Location;

use Illuminate\Support\Facades\App;
use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Http\Kernel;

class LocationServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot(Kernel $kernel)
    {
        $this->publishes([
            __DIR__.'/config/location.php' => config_path('location.php'),
        ], 'location');
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        App::bind('location', function()
        {
            return new \Thorazine\Location\Classes\Facades\Location;
        });

        $this->mergeConfigFrom(__DIR__.'/config/location.php', 'location');
    }
}