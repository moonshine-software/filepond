<?php

declare(strict_types=1);

namespace VendorName\PackageName\Testing;

use Illuminate\Support\ServiceProvider;
use MoonShine\Contracts\Core\DependencyInjection\ConfiguratorContract;
use MoonShine\Contracts\Core\DependencyInjection\CoreContract;
use MoonShine\Laravel\Resources\MoonShineUserResource;
use MoonShine\Laravel\Resources\MoonShineUserRoleResource;

final class TestingServiceProvider extends ServiceProvider
{
    public function boot(CoreContract $core, ConfiguratorContract $config): void
    {
        $core->resources([
            MoonShineUserResource::class,
            MoonShineUserRoleResource::class,
        ])->pages($config->getPages());
    }
}
