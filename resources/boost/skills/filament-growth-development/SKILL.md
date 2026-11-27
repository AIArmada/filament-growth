---
name: filament-growth-development
description: Develop filament-growth integrations using owner-safe Growth operations and Laravel Dune package boundaries.
---

# Filament Growth

Filament Growth provides administration and reporting for the Growth experiment aggregate; it owns no independent domain tables. Keep durable mutations in Growth's `SaveExperiment`, `SaveVariant`, and `UpdateGrowthSettings` actions. Filament only authorizes and delegates; use `AIArmada\Growth\Queries\ExperimentOwnership` for owner-scoped domain queries. Read-only UI analytics belong in Queries and policy decisions in Authorization. Resources, pages and widgets are presentation entry points. All mutable experiment and variant operations must respect owner scope and the configured authorization policies. Never re-open concluded or archived experiments through the running toggle. Authorize every resource, page, table, widget and action with `Gate::forUser()` using `Filament::auth()->user()`, never the ambient default guard. The settings page requires explicit global owner context when Growth or Signals ownership is enabled.

## Verification

From the Commerce monorepo root, run `composer --working-dir=packages/filament-growth arch:check`, the affected Pest tests, PHPStan and Pint. Update callers directly when public APIs move; do not introduce compatibility wrappers.
