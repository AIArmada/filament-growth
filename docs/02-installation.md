---
title: Installation
---

# Installation

## Install the package

```bash
composer require aiarmada/filament-growth
```

The package auto-registers its service provider through Laravel package discovery.

## Register the Filament plugin

Register the plugin on the panel where you want the Growth UI to appear:

```php
use AIArmada\FilamentGrowth\FilamentGrowthPlugin;
use Filament\Panel;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugin(FilamentGrowthPlugin::make());
}
```

## Publish the configuration file

```bash
php artisan vendor:publish --tag=filament-growth-config
```

This creates `config/filament-growth.php`.

## Install the domain package first

`filament-growth` is the admin surface only. It expects the underlying Growth package to be installed and working:

- [`aiarmada/growth`](../../growth/docs/02-installation.md)
- Signals tracked properties available from [`aiarmada/signals`](../../signals/docs/01-overview.md)
- owner resolution configured if your application runs in owner-scoped mode

The package does not ship its own database tables or migrations.

## Verify the installation

After registering the plugin, confirm that your panel shows the enabled Growth navigation items for an authenticated user who can `viewAny` experiments:

- dashboard
- results page
- experiments resource
- variants resource

If one of these items is missing, check the feature flags in [configuration](./03-configuration.md).