---
title: Troubleshooting
---

# Troubleshooting

## Nothing appears in the Filament navigation

**Cause:** The plugin is not registered on the panel, the relevant feature flags are disabled, or the current user is not allowed to `viewAny` experiments.

**Fix:**

1. Register `FilamentGrowthPlugin::make()` on the target panel.
2. Check `config/filament-growth.php`.
3. Make sure the specific feature is enabled:
   - `features.dashboard`
   - `features.results`
   - `features.experiments`
   - `features.variants`

## The dashboard loads, but widgets are missing

**Cause:** `features.widgets` is `false`.

**Fix:** Enable `filament-growth.features.widgets`.

## The results page or widgets show empty data instead of crashing

**Cause:** `ExperimentResultsPage`, `GrowthStatsWidget`, and `ExperimentWinnersWidget` fail softly when `AggregateExperimentMetrics` throws.

**Fix:** Inspect the underlying Growth experiment, tracked property, and Signals attribution data. The empty state usually points to bad or inaccessible experiment metrics rather than a Filament rendering bug.

## Tracked property or experiment selects are empty

**Cause:** The form queries are owner-scoped, so inaccessible records are filtered out.

**Fix:**

- verify the current owner is resolved correctly
- ensure the tracked property belongs to the same owner as the experiment flow
- confirm the underlying Growth and Signals records exist for that owner

## The results page shows no winner yet

**Cause:** The selected experiment has no assignments yet, or it lacks qualifying attributed events.

**Expected behavior:** the page shows a pending state until `AggregateExperimentMetrics` returns a real `winner_variant_id`.

## The dashboard says revenue is `Mixed`

**Cause:** The visible experiments use tracked properties with different currencies.

**Expected behavior:** `GrowthStatsWidget` switches to `Mixed` and lists per-currency totals in the description.

Remember that the widget only aggregates revenue and winner-ready summaries from the most recently updated visible experiments up to `filament-growth.tables.stats_experiment_limit`.

## Variant settings fields do not appear

**Cause:** The fields are driven by the selected experiment's module type.

**Fix:**

- select an experiment first
- confirm the experiment uses one of the supported module types
- verify you are not looking for settings from a different module preset

## Results links open, but no experiment data loads

**Cause:** The requested experiment is outside the current owner scope or was deleted.

**Fix:** Open the page from the current experiment list, or resolve the owner context before trying again.

## More help

- Review [installation](./02-installation.md)
- Review [configuration](./03-configuration.md)
- Review [usage](./04-usage.md)
- Check the underlying [`growth` troubleshooting guide](../../growth/docs/99-troubleshooting.md)