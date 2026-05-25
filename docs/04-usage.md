---
title: Usage
---

# Usage

## Register the plugin on your panel

Once installed, register `FilamentGrowthPlugin` on the panel where you want the Growth admin UI to live.

```php
use AIArmada\FilamentGrowth\FilamentGrowthPlugin;
use Filament\Panel;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugin(FilamentGrowthPlugin::make());
}
```

## Manage experiments

`ExperimentResource` gives you CRUD screens for experiments with owner-safe queries, tracked-property consistency checks, relation counts, and gated mutation checks.

### Experiment form highlights

- `name` and `slug`
- owner-scoped `tracked_property_id`
- `module_type`
- `status`
- `goal_event_name`
- `goal_event_category`
- `winner_metric`
- `description`

When `growth.features.preset_modules.enabled` is enabled, changing the module type pre-fills goal and settings fields using `ResolveExperimentPreset`.

The tracked-property selector only shows writable Signals properties that are visible in the current owner scope and consistent with the experiment's owner tuple.

### Module settings UI

The experiment form exposes module-specific settings for supported presets:

- `sales_page_test`: entry paths, destination URLs, CTA event name
- `funnel_test`: funnel steps repeater
- `pricing_test`: checkout event name and price labels

The resource table includes counts for variants and assignments, plus a direct `Results` action for each experiment.

Bulk deletes are transactional and reject mixed selections that include records the current context is not allowed to delete, including global records outside explicit global context.

## Manage variants

`VariantResource` lets operators configure traffic allocation and module-specific variant settings.

### Variant form highlights

- owner-scoped experiment selector
- `code` and `name`
- `traffic_percentage`
- `position`
- `is_control`
- `is_active`
- `description`

### Variant settings UI

The visible settings fields depend on the selected experiment's module type:

- `sales_page_test`: destination URL, headline, CTA copy
- `funnel_test`: entry path, step key, offer label
- `pricing_test`: price label, price in minor units, currency

If no experiment is selected, module-specific settings stay hidden.

Bulk deletes follow the same safety rule as experiments: all selected rows must be deletable in the current context before any record is removed.

## Review experiment results

`ExperimentResultsPage` is the reporting surface for a single experiment.

### What the page shows

- experiment selector
- metric selector
- summary cards for assignments, purchases, tracked revenue, and winner metric
- winner summary card when a winner exists
- pending state when `winner_variant_id` is `null`
- per-variant comparison chart
- full variant breakdown table
- support for `?experiment=<id>` deep links from resource actions and widgets

The page reads from `AggregateExperimentMetrics`, so it stays aligned with the same winner and attribution rules used by the domain package.

If metric aggregation throws, the page fails softly and renders empty results instead of crashing the panel.

## Use the Growth dashboard

`GrowthDashboard` provides the overview page for the package.

### Growth stats widget

`GrowthStatsWidget` summarizes:

- active experiments
- total variants
- total assignments
- tracked revenue

If experiments use multiple currencies, tracked revenue is shown as `Mixed` with per-currency details in the description.

Tracked revenue and winner-ready summaries are aggregated from the most recently updated visible experiments, capped by `filament-growth.tables.stats_experiment_limit`.

### Recent winners widget

`ExperimentWinnersWidget` shows the five most recently updated experiments with:

- module label
- experiment status
- current winner name or `Pending`
- winner metric label and value
- total tracked revenue
- assignment count

Each row links back to the results page when available.

The link preselects the experiment on the results page via the `experiment` query-string parameter.

If aggregation fails for a visible experiment, that row is skipped instead of breaking the widget.

## Owner scoping expectations

The package resolves readable and writable records through `AccessibleGrowthRecords`, and the policies delegate to the same access rules.

That means:

- tracked properties must belong to the current owner scope
- experiments must belong to the current owner scope
- tracked-property ownership must remain consistent with the experiment owner tuple
- result pages only load experiments the current owner can access
- dashboard pages and widgets require an authenticated user who can `viewAny` experiments

If an experiment and its tracked property no longer line up, `AccessibleGrowthRecords` intentionally hides that record from the Filament layer.

For multi-tenant applications, make sure your owner resolver is configured through [`commerce-support`](../../commerce-support/docs/04-multi-tenancy.md).