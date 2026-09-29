---
title: Filament Growth Context
package: filament-growth
status: current
surface: filament
family: growth-and-incentives
keywords:
  - filament
  - experiments-ui
  - ab-test
---

# Filament Growth Context

## Snapshot
- Composer: `aiarmada/filament-growth`
- Role: Filament admin for experiments/variants, dashboards, results.
- Triggers: filament, experiments-ui, ab-test
- Search first: `src/Resources, src/Pages, src/Widgets, config, docs`
- Related: `growth`, `signals`, `filament-signals`
- Paired: `growth` (core domain owner)

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../growth/CONTEXT.md` when the change crosses UI/domain
6. `docs/02-installation.md` when setup or publishing changes are involved

## Guardrails
- Adapter only: no domain models/actions/calculations. Keep all business rules in `growth`.
- Filament tenancy is not a security boundary; revalidate every submitted ID server-side (owner scope).
- If behavior or calculations change, move them to `growth` and keep this package UI-only.
- Update `docs/*.md` in the same pass when public behavior or config changes.

## Decide fast
- Use when: Experiment admin UI.
- Skip when: Assignment math — see growth.
- Owner/security: ExperimentHelpers owner-aware.

## Key surfaces
- Resources: `ExperimentResource`, `VariantResource`
- Actions/Services: `Support/ExperimentHelpers`, `Support/GrowthStatsAggregator`
- Config `filament-growth.php`: `navigation`, `group`, `tables`, `stats_experiment_limit`, `features`, `dashboard`, `results`, `widgets`, `experiments`, `variants`

## Docs map
- Start: `01-overview` → `03-configuration` → `04-usage` → `99-troubleshooting`
- Deep dives: none — the five canonical docs cover this package
