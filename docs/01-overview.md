---
title: Overview
---

# Filament Growth

## Purpose

The `aiarmada/filament-growth` package is the Filament admin adapter for `aiarmada/growth`.

## What this package owns

- Filament resources for experiments and variants
- Growth dashboard, experiment results page, and summary widgets
- Filament-facing policy-gated admin workflows for experimentation management
- Owner-safe read paths that only surface experiments with consistent tracked-property access

## What this package does not own

- Experiment assignment, Signals enrichment, or metrics aggregation rules; those stay in `aiarmada/growth`
- Signals event storage and tracked-property ownership
- Tenant resolution itself; it consumes owner context from the host app and `commerce-support`

## Related packages

- [`aiarmada/growth`](../../growth/docs/01-overview.md) — core experimentation engine
- [`aiarmada/signals`](../../signals/docs/01-overview.md) — tracked properties and event attribution
- [`aiarmada/commerce-support`](../../commerce-support/docs/04-multi-tenancy.md) — owner resolution and scoping primitives

## Main models services or surfaces

- **Resources** — `ExperimentResource`, `VariantResource`
- **Pages** — `GrowthDashboard`, `ExperimentResultsPage`
- **Widgets** — `GrowthStatsWidget`, `ExperimentWinnersWidget`

## Owner scoping and security notes

- The plugin should mirror the owner-scoping behavior defined by `aiarmada/growth`
- Dashboard and results filters are not authorization; the backing growth package remains responsible for safe reads and writes inside the current owner scope

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
- Results page backed by `AggregateExperimentMetrics` with deep links to a selected experiment
- Dashboard widgets that handle mixed-currency revenue summaries and pending winner states
- Fail-soft reporting surfaces that render empty or partial states when aggregation throws
- Accessible-record guards that hide inconsistent experiment / tracked-property combinations instead of leaking cross-owner data

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

## Read next

- [Installation](02-installation.md)
- [Configuration](03-configuration.md)
- [Usage](04-usage.md)
- [Troubleshooting](99-troubleshooting.md)
- [Core growth overview](../../growth/docs/01-overview.md)