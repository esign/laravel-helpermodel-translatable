<?php

namespace Esign\HelperModelTranslatable;

use Illuminate\Support\ServiceProvider;

class HelperModelTranslatableServiceProvider extends ServiceProvider
{
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                $this->configPath() => config_path('helpermodel-translatable.php'),
            ], 'config');
        }
    }

    public function register()
    {
        $this->mergeConfigFrom($this->configPath(), 'helpermodel-translatable');
    }

    protected function configPath(): string
    {
        return __DIR__ . '/../config/helpermodel-translatable.php';
    }
}