<?php

namespace InWeb\Payment\Maib;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    protected static $packagePath = __DIR__ . '/../../';
    protected static $packageAlias = 'payment-maib';

    public static function getPackageAlias()
    {
        return self::$packageAlias;
    }

    public static function getPackagePath()
    {
        return self::$packagePath;
    }

    /**
     * Bootstrap any package services.
     *
     * @param Router $router
     * @return void
     */
    public function boot(Router $router)
    {
        if ($this->app->runningInConsole()) {
            $this->registerPublishing();
        }
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->registerResources();
    }

    /**
     * Register the package resources such as routes, templates, etc.
     *
     * @return void
     */
    protected function registerResources()
    {
        //
    }

    private function registerPublishing()
    {
        // Config
        $this->publishes([
            self::$packagePath . 'config/config.php' => config_path(self::$packageAlias . '.php'),
        ], 'config');
    }
}
