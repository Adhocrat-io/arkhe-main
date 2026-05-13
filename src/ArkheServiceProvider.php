<?php

declare(strict_types=1);

namespace Adhocrat\Arkhe;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class ArkheServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('arkhe')
            ->hasConfigFile('arkhe')
            ->hasViews('arkhe')
            ->hasTranslations()
            ->hasRoute('arkhe')
            ->hasMigration('add_arkhe_profile_columns_to_users_table');
    }

    public function packageBooted(): void
    {
        // Component registration, middleware aliasing and bindings
        // are wired up in subsequent commits.
    }
}
