<?php

namespace CatLab\Assets\Laravel;

use Illuminate\Support\ServiceProvider;
use League\Flysystem\Config;

/**
 * Class AssetServiceProvider
 * @package CatLab\Assets
 */
class AssetServiceProvider extends ServiceProvider
{
    /**
     *
     */
    public function register()
    {

    }

    /**
     * Perform post-registration booting of services.
     * @return void
     */
    public function boot()
    {
        if (! $this->app->routesAreCached()) {
            if (\Config::get('assets.route')) {
                require __DIR__ . '/routes.php';
            }
        }

        //$namespace = 'assets';
        //$resourcePath = __DIR__.'/../resources/';

        //$this->loadViewsFrom($resourcePath . 'views', $namespace);
        //$this->loadTranslationsFrom($resourcePath . 'lang', $namespace);

        /*
        $this->publishes([

        ], 'resources');
        */

        $this->publishes([
            __DIR__.'/../../config/assets.php' => config_path('assets.php'),
        ], 'config');

        $this->publishes([
            __DIR__ . '/../../database/migrations' => database_path('migrations')
        ], 'migrations');
    }
}