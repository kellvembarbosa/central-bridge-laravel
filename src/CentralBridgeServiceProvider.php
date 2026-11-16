<?php

namespace Applu\CentralBridge;

use Applu\CentralBridge\Client\CentralClient;
use Applu\CentralBridge\Support\HandlerRegistry;
use Illuminate\Support\ServiceProvider;

class CentralBridgeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/central-bridge.php', 'central-bridge');

        $this->app->singleton(HandlerRegistry::class, fn ($app) => new HandlerRegistry($app));
        $this->app->bind(CentralClient::class, fn () => CentralClient::fromConfig());
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/central-bridge.php' => config_path('central-bridge.php'),
            ], 'central-bridge-config');
        }
    }
}
