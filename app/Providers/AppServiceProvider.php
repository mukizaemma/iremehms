<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Override Laravel Ignition's context provider detector to fix Livewire v4 compatibility
        // This prevents the ComponentRegistry error when using Livewire v4
        $this->app->singleton(
            \Spatie\LaravelIgnition\ContextProviders\LaravelContextProviderDetector::class,
            \App\ContextProviders\LaravelContextProviderDetector::class
        );
        
        // Override ErrorPageRenderer to use our custom detector
        $this->app->bind(
            \Spatie\LaravelIgnition\Renderers\ErrorPageRenderer::class,
            \App\Renderers\ErrorPageRenderer::class
        );
        
        // Also override IgnitionExceptionRenderer to use our custom ErrorPageRenderer
        $this->app->bind(
            \Spatie\LaravelIgnition\Renderers\IgnitionExceptionRenderer::class,
            function ($app) {
                return new \Spatie\LaravelIgnition\Renderers\IgnitionExceptionRenderer(
                    $app->make(\Spatie\LaravelIgnition\Renderers\ErrorPageRenderer::class)
                );
            }
        );
        
        // Also override the Flare singleton to use our custom detector
        $this->app->extend(\Spatie\FlareClient\Flare::class, function ($flare) {
            return $flare->setContextProviderDetector(
                app(\App\ContextProviders\LaravelContextProviderDetector::class)
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
