---
title: Overview
---

# Filament Growth

`aiarmada/filament-growth` provides a Filament v5 admin UI for the `aiarmada/growth` experimentation engine. It adds owner-scoped resources, a dashboard, a results page, and summary widgets so operators can manage experiments and inspect performance without building a custom back office first.

## What the package adds

| Surface | Purpose |
| --- | --- |
| `ExperimentResource` | Create, edit, filter, and review experiments |
| `VariantResource` | Manage variants and module-specific settings |
| `GrowthDashboard` | High-level growth summary page |
| `ExperimentResultsPage` | Winner summary, per-variant chart, and results breakdown |
| `GrowthStatsWidget` | Active experiment, variant, assignment, and revenue totals |
| `ExperimentWinnersWidget` | Recent experiments with current winner or pending status |

## Package features

- Filament plugin registration through `FilamentGrowthPlugin`
- Owner-scoped experiment and variant queries by default
- Policy-gated resources, pages, and widgets backed by `ExperimentPolicy` and `VariantPolicy`
- Preset-aware experiment forms powered by `ResolveExperimentPreset`
- Module-aware variant settings based on the selected experiment type
- Results page backed by `AggregateExperimentMetrics`
- Dashboard widgets that handle mixed-currency revenue summaries and pending winner states
- Fail-soft reporting surfaces that render empty or partial states when aggregation throws

## Requirements

- Filament v5
- [`aiarmada/growth`](../../growth/docs/01-overview.md)
- [`aiarmada/commerce-support`](../../commerce-support/docs/01-overview.md) for owner scoping primitives

## Related packages

- [`aiarmada/growth`](../../growth/docs/01-overview.md) for the experiment, variant, assignment, and metrics domain layer
- [`aiarmada/signals`](../../signals/docs/01-overview.md) for tracked properties and event attribution
- [`aiarmada/commerce-support`](../../commerce-support/docs/04-multi-tenancy.md) for owner resolution and scoping behavior

## Next steps

- Start with [installation](./02-installation.md)
- Review [configuration](./03-configuration.md)
- Walk through [usage](./04-usage.md)
- Keep [troubleshooting](./99-troubleshooting.md) handy for owner-scope and navigation issues