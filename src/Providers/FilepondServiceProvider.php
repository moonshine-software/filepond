<?php

declare(strict_types=1);

namespace MoonShine\Filepond\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use MoonShine\Filepond\Http\Controllers\FilepondController;

final class FilepondServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'moonshine-filepond');
        $this->loadTranslationsFrom(__DIR__ . '/../../lang', 'moonshine-filepond');

        $this->registerRoutes();

        $this->publishes([
            __DIR__ . '/../../dist' => public_path('vendor/moonshine-filepond'),
        ], ['moonshine-filepond-assets', 'laravel-assets']);

        $this->publishes([
            __DIR__ . '/../../lang' => lang_path('vendor/moonshine-filepond'),
        ], 'moonshine-filepond-lang');
    }

    protected function registerRoutes(): void
    {
        Route::moonshine(static function (): void {
            Route::prefix('filepond')
                ->name('filepond.')
                ->group(function () {
                    Route::post('/upload', [FilepondController::class, 'upload'])->name('upload');
                });
        }, withResource: true, withPage: true, withAuthenticate: true);
    }
}
