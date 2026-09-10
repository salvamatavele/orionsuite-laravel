<?php

namespace OrionSuite\Laravel;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use OrionSuite\Identity\OrionKycClient;
use OrionSuite\Laravel\Http\Controllers\PagarWebhookController;
use OrionSuite\Notifications\NotificaClient;
use OrionSuite\OrionSuiteManager;
use OrionSuite\Payments\PagarClient;

class OrionSuiteServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/orionsuite.php', 'orionsuite');

        $this->app->singleton(PagarClient::class, function ($app): PagarClient {
            return PagarClient::fromConfig($app['config']['orionsuite']['pagar'] ?? []);
        });

        $this->app->singleton(NotificaClient::class, function ($app): NotificaClient {
            return NotificaClient::fromConfig($app['config']['orionsuite']['notifica'] ?? []);
        });

        $this->app->singleton(OrionKycClient::class, function ($app): OrionKycClient {
            return OrionKycClient::fromConfig($app['config']['orionsuite']['kyc'] ?? []);
        });

        $this->app->singleton(OrionSuiteManager::class, function ($app): OrionSuiteManager {
            return new OrionSuiteManager(
                $app->make(PagarClient::class),
                $app->make(NotificaClient::class),
                $app->make(OrionKycClient::class),
            );
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/orionsuite.php' => config_path('orionsuite.php'),
            ], 'orionsuite-config');

            $this->publishes([
                __DIR__.'/../../resources/js/components/PagarModal.tsx' => resource_path('js/components/PagarModal.tsx'),
            ], 'orionsuite-react');
        }

        $this->registerRoutes();
    }

    protected function registerRoutes(): void
    {
        if (config('orionsuite.pagar.webhook.enabled', true)) {
            $path = config('orionsuite.pagar.webhook.path', '/api/webhooks/pagar');

            Route::post($path, PagarWebhookController::class)
                ->name('orionsuite.pagar.webhook');
        }
    }
}
