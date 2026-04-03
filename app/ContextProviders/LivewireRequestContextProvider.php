<?php

namespace App\ContextProviders;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Livewire\LivewireManager;
use Spatie\LaravelIgnition\ContextProviders\LaravelRequestContextProvider;

class LivewireRequestContextProvider extends LaravelRequestContextProvider
{
    public function __construct(
        Request $request,
        protected ?LivewireManager $livewireManager = null
    ) {
        parent::__construct($request);
        
        // Get LivewireManager if not provided
        if (!$this->livewireManager && app()->bound(LivewireManager::class)) {
            $this->livewireManager = app(LivewireManager::class);
        }
    }

    /** @return array<string, string> */
    public function getRequest(): array
    {
        $properties = parent::getRequest();

        if ($this->livewireManager) {
            try {
                $properties['method'] = $this->livewireManager->originalMethod();
                $properties['url'] = $this->livewireManager->originalUrl();
            } catch (Exception $e) {
                // Ignore errors
            }
        }

        return $properties;
    }

    /** @return array<int|string, mixed> */
    public function toArray(): array
    {
        $properties = parent::toArray();

        $properties['livewire'] = $this->getLivewireInformation();

        return $properties;
    }

    /** @return array<int, mixed> */
    protected function getLivewireInformation(): array
    {
        if (!$this->livewireManager) {
            return [];
        }

        if ($this->request->has('components')) {
            $data = [];

            foreach ($this->request->get('components') as $component) {
                try {
                    $snapshot = json_decode($component['snapshot'] ?? '{}', true);

                    // Try to get component class - handle Livewire v4 compatibility
                    $class = null;
                    try {
                        // Livewire v4 - use Factory to resolve component class
                        $componentName = $snapshot['memo']['name'] ?? null;
                        if ($componentName && $this->livewireManager) {
                            // Use Factory to resolve component class
                            $factory = app('livewire.factory');
                            if (method_exists($factory, 'resolveComponentClass')) {
                                $class = $factory->resolveComponentClass($componentName);
                            } elseif (method_exists($factory, 'resolveComponentNameAndClass')) {
                                [$name, $class] = $factory->resolveComponentNameAndClass($componentName);
                            }
                        }
                    } catch (Exception $e) {
                        // Ignore errors when getting component class
                    }

                    $componentUpdates = $component['updates'] ?? [];
                    if (!is_array($componentUpdates)) {
                        $componentUpdates = [];
                    }
                    
                    $data[] = [
                        'component_class' => $class ?? null,
                        'data' => $snapshot['data'] ?? [],
                        'memo' => $snapshot['memo'] ?? [],
                        'updates' => $this->resolveUpdates($componentUpdates),
                        'calls' => $component['calls'] ?? [],
                    ];
                } catch (Exception $e) {
                    // Skip invalid components
                    continue;
                }
            }

            return $data;
        }

        /** @phpstan-ignore-next-line */
        $componentId = $this->request->input('fingerprint.id');

        /** @phpstan-ignore-next-line */
        $componentAlias = $this->request->input('fingerprint.name');

        if ($componentAlias === null) {
            return [];
        }

        try {
            // Livewire v4 - use Factory to resolve component class
            $factory = app('livewire.factory');
            if (method_exists($factory, 'resolveComponentClass')) {
                $componentClass = $factory->resolveComponentClass($componentAlias);
            } elseif (method_exists($factory, 'resolveComponentNameAndClass')) {
                [$name, $componentClass] = $factory->resolveComponentNameAndClass($componentAlias);
            } else {
                $componentClass = null;
            }
        } catch (Exception $e) {
            $componentClass = null;
        }

        /** @phpstan-ignore-next-line */
        $updates = $this->request->input('updates') ?? [];
        
        // Ensure updates is an array
        if (!is_array($updates)) {
            $updates = [];
        }

        return [
            [
                'component_class' => $componentClass,
                'component_alias' => $componentAlias,
                'component_id' => $componentId,
                'data' => $this->resolveData(),
                'updates' => $this->resolveUpdates($updates),
            ],
        ];
    }

    /** @return array<string, mixed> */
    protected function resolveData(): array
    {
        /** @phpstan-ignore-next-line */
        $data = $this->request->input('serverMemo.data') ?? [];

        /** @phpstan-ignore-next-line */
        $dataMeta = $this->request->input('serverMemo.dataMeta') ?? [];

        foreach ($dataMeta['modelCollections'] ?? [] as $key => $value) {
            $data[$key] = array_merge($data[$key] ?? [], $value);
        }

        foreach ($dataMeta['models'] ?? [] as $key => $value) {
            $data[$key] = array_merge($data[$key] ?? [], $value);
        }

        return $data;
    }

    /** @return array<string, mixed> */
    protected function resolveUpdates($updates): array
    {
        // Ensure $updates is an array
        if (!is_array($updates)) {
            return [];
        }

        return array_map(function ($update) {
            // Ensure each update is an array
            if (!is_array($update)) {
                return [];
            }
            
            $update['payload'] = Arr::except($update['payload'] ?? [], ['id']);

            return $update;
        }, $updates);
    }
}
