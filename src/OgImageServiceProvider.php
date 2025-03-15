<?php

namespace Backstage\OgImage\Laravel;

use Backstage\OgImage\Laravel\Commands\ClearCache;
use Backstage\OgImage\Laravel\View\Components\OgImageComponent;
use Illuminate\Support\Facades\Blade;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class OgImageServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('og-image')
            ->hasRoute('web')
            ->hasConfigFile()
            ->hasViews()
            ->hasCommand(ClearCache::class)
            ->hasInstallCommand(function (InstallCommand $command) {
                $command
                    ->publishConfigFile()
                    ->askToStarRepoOnGitHub('backstagephp/laravel-og-image');
            });
    }

    public function packageRegistered(): void
    {
        Blade::component('og-image', OgImageComponent::class);
    }
}
