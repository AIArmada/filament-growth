---
title: Configuration
---

# Configuration

All package options live in `config/filament-growth.php`.

## Configuration structure

```php
return [
    'navigation' => [
        'group' => 'Growth'
    ],

    'tables' => [
        'stats_experiment_limit' => 10,
    ],

    'features' => [
        'dashboard' => true,
        'results' => true,
        'widgets' => true,
        'experiments' => true,
        'variants' => true,
        'settings_page' => true,
    ],

    'resources' => [
        'navigation_sort' => [
            'dashboard' => 10,
            'results' => 11,
            'experiments' => 20,
            'variants' => 21,
            'settings' => 99,
        ],
    ],
];
```

## Navigation

### `navigation.group`

Controls the Filament navigation group used by the dashboard, results page, and resources.

### `navigation.settings_group`

Controls the Filament navigation group used by the `ManageGrowthSettings` page. Set it when settings pages live in a different navigation group from the Growth resources; it defaults to `Growth`.

## Tables

### `tables.stats_experiment_limit`

Limits how many of the most recently updated visible experiments `GrowthStatsWidget` will aggregate when building tracked revenue and winner-ready summaries.

This does not change the all-record counts for active experiments, variants, or assignments.

## Features

### `features.dashboard`

Registers the `GrowthDashboard` page in the panel.

### `features.results`

Registers the `ExperimentResultsPage`, enables the dashboard header action that links to results, and allows the per-row `Results` action on `ExperimentResource`.

### `features.widgets`

Allows `GrowthDashboard` to attach:

- `GrowthStatsWidget`
- `ExperimentWinnersWidget`

This flag only affects widget registration on the dashboard page.

### `features.experiments`

Registers `ExperimentResource`.

### `features.variants`

Registers `VariantResource`.

### `features.settings_page`

Registers the `ManageGrowthSettings` page. The page also stays hidden unless the signed-in
user passes the `growth.settings.manage` Gate ability and, when Growth or Signals ownership is enabled, the panel has an explicit global owner context. See [usage](./04-usage.md).
When Growth ownership is enabled, the page saves through an explicit global owner context because the middleware kill switch is global.

## Resources

### `resources.navigation_sort`

Controls the order of registered navigation items:

- `dashboard`
- `results`
- `experiments`
- `variants`
- `settings`

## Example custom configuration

```php
<?php

return [
    'navigation' => [
        'group' => 'Growth'
    ],

    'tables' => [
        'stats_experiment_limit' => 5,
    ],

    'features' => [
        'dashboard' => true,
        'results' => true,
        'widgets' => true,
        'experiments' => true,
        'variants' => false,
        'settings_page' => true,
    ],

    'resources' => [
        'navigation_sort' => [
            'dashboard' => 30,
            'results' => 31,
            'experiments' => 40,
            'variants' => 41,
            'settings' => 99,
        ],
    ],
];
```

## Related reading

- [Installation](./02-installation.md)
- [Usage](./04-usage.md)
- [`growth` configuration](../../growth/docs/03-configuration.md)