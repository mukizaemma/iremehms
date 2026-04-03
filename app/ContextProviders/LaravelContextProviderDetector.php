<?php

namespace App\ContextProviders;

use Illuminate\Http\Request;
use Livewire\LivewireManager;
use Spatie\FlareClient\Context\ContextProvider;
use Spatie\FlareClient\Context\ContextProviderDetector;
use Spatie\LaravelIgnition\ContextProviders\LaravelConsoleContextProvider;
use Spatie\LaravelIgnition\ContextProviders\LaravelRequestContextProvider;

class LaravelContextProviderDetector implements ContextProviderDetector
{
    public function detectCurrentContext(): ContextProvider
    {
        if (app()->runningInConsole()) {
            return new LaravelConsoleContextProvider($_SERVER['argv'] ?? []);
        }

        $request = app(Request::class);

        if ($this->isRunningLiveWire($request)) {
            try {
                // Use our custom Livewire context provider that doesn't use ComponentRegistry
                return new LivewireRequestContextProvider(
                    $request,
                    app()->bound(LivewireManager::class) ? app(LivewireManager::class) : null
                );
            } catch (\Exception $e) {
                // Fallback to base provider if Livewire isn't available
                return new LaravelRequestContextProvider($request);
            }
        }

        return new LaravelRequestContextProvider($request);
    }

    protected function isRunningLiveWire(Request $request): bool
    {
        // Check multiple ways Livewire requests can be identified
        return $request->hasHeader('x-livewire') && $request->hasHeader('referer')
            || $request->has('components')
            || $request->has('fingerprint')
            || $request->header('X-Livewire') === 'true'
            || str_contains($request->header('Content-Type', ''), 'application/x-livewire');
    }
}
