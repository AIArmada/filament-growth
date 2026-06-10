---
title: Lifecycle
---

# Lifecycle

## 1. Package Identity

`aiarmada/filament-growth` is the Filament v5 admin UI adapter for `aiarmada/growth`. It owns Filament resources, pages, widgets, tables, forms, and panel plugin glue. It does **not** own experiment assignment, Signals enrichment, metrics aggregation rules, or tenant resolution — those live in `aiarmada/growth`, `aiarmada/signals`, and `aiarmada/commerce-support`.

| Artifact | Value |
| --- | --- |
| Composer | `aiarmada/filament-growth` |
| PHP namespace | `AIArmada\FilamentGrowth` |
| Filament plugin ID | `filament-growth` |
| Navigation group | `Growth` (configurable) |
| Config key | `filament-growth` |

## 2. Dependencies & Contract Surface

### Hard dependencies

| Package | Consumed surface | Purpose |
| --- | --- | --- |
| `aiarmada/growth` | `Experiment`, `Variant`, `Assignment` models | CRUD subjects |
| `aiarmada/growth` | `ExperimentStatus`, `ExperimentModuleType` enums | Status/type display and state logic |
| `aiarmada/growth` | `AggregateExperimentMetrics`, `ResolveExperimentPreset` actions | Metrics rendering, form presets |
| `aiarmada/signals` | `TrackedProperty` model | Experiment ownership chain, owner-scoped queries |
| `aiarmada/commerce-support` | `OwnerUiScope`, `OwnerScope`, `OwnerContext`, `OwnerScopeKey` | Owner-safe read/write paths |
| `aiarmada/commerce-support` | `FormatsMoney` trait | Currency-aware revenue display |

### Contract expectations

- **`aiarmada/growth`** must publish `Experiment`, `Variant`, `Assignment` with `HasOwner` trait and `ownerScopeConfig()`.
- **`aiarmada/signals`** must publish `TrackedProperty` with `HasOwner`.
- **`aiarmada/commerce-support`** must resolve an `OwnerResolverInterface` binding.

## 3. Registration Lifecycle

### 3.1 Service provider boot

`FilamentGrowthServiceProvider`:
1. **`configurePackage()`**: sets package name, registers views, publishes config.
2. **`packageRegistered()`**: binds `FilamentGrowthPlugin` as a singleton.
3. **`packageBooted()`**: registers `Gate::policy()` for `Experiment` → `ExperimentPolicy` and `Variant` → `VariantPolicy`.

### 3.2 Panel registration

When `$panel->plugin(FilamentGrowthPlugin::make())` is called, `register()` conditionally adds surfaces:

```
Panel::register()
├── if features.dashboard    → $panel->pages([GrowthDashboard::class])
├── if features.results      → $panel->pages([ExperimentResultsPage::class])
├── if features.settings_page → $panel->pages([ManageGrowthSettings::class])
├── if features.experiments  → $panel->resources([ExperimentResource::class])
└── if features.variants     → $panel->resources([VariantResource::class])
```

Each surface independently gates navigation visibility through `shouldRegisterNavigation()` → `canAccess()` → `ExperimentPolicy::viewAny()` gate.

### 3.3 Navigation ordering

| Surface | Config key | Default |
| --- | --- | --- |
| Growth Dashboard | `navigation_sort.dashboard` | `10` |
| Experiment Results | `navigation_sort.results` | `11` |
| Experiments | `navigation_sort.experiments` | `20` |
| Variants | `navigation_sort.variants` | `21` |
| Settings | `navigation_sort.settings` | `99` |

## 4. Experiment Resource Lifecycle

### 4.1 List (index)

`ListExperiments` renders an owner-scoped table via `ExperimentsTable::configure()`.

**Query pipeline** (`getEloquentQuery()`):
1. `OwnerUiScope::apply(Experiment::query())` — restricts to current owner scope.
2. `ExperimentHelpers::applyOwnerSafeRelationCounts()` — adds `variants_count` and `assignments_count` via correlated subqueries matching owner tuples (handles both owned and global rows using `withoutGlobalScope()` on child models).
3. Eager-loads `trackedProperty` (id + name only, owner-scoped).

**Columns**: name (with slug description), tracked property name, module type badge, status badge with description, running toggle (Active/Paused), goal badge, variants count, assignments count, created_at (togglable).

**Running toggle**: Only enabled when `OwnerUiScope::canMutateRecord()` passes. Concluded experiments are locked.

**Filtering**: Status filter (SelectFilter on `ExperimentStatus` values). Tracked property name search uses correlated subquery with owner tuple matching.

### 4.2 Create

`CreateExperiment` uses `ExperimentForm::configure()`.

**Form sections**:
1. **Experiment**: name (auto-generates slug, `live(onBlur: true)`), slug (alpha-dash, scoped-unique via `owner_scope` modifier), tracked_property_id (owner-scoped, disabled on edit), module_type (live-updates goal/winner/settings via `ResolveExperimentPreset`), status (Draft default).
2. **Outcome**: goal_event_name, goal_event_category, winner_metric, description.
3. **Module Settings**: conditionally visible per module_type (SalesPageTest, FunnelTest, PricingTest). Only visible when `growth.features.preset_modules.enabled`.

**Slug uniqueness**: Scoped to owner using `OwnerScopeKey::forOwner(OwnerContext::resolve())`.

**Module type preset application**: When `module_type` changes, `ResolveExperimentPreset::handle()` reads preset from `config('growth.defaults.presets.<type>')` and populates goal fields and settings.

### 4.3 Edit

`EditExperiment`: Same form but `tracked_property_id` is `->disabledOn('edit')` (immutable after creation).

**Access control**: `canEdit()` checks `OwnerUiScope::canMutateRecord()` first, then falls back to `ExperimentHelpers::canMutateViaTrackedProperty()`.

### 4.4 Delete

**Single delete**: `canDelete()` applies the same dual check as `canEdit()`.

**Bulk delete**: Runs inside `DB::transaction`. Each record validated via `OwnerUiScope::canMutateRecord()`. Global (owner-less) experiments throw `RuntimeException` unless in explicit global context.

## 5. Variant Resource Lifecycle

### 5.1 List (index)

**Query pipeline** (`getEloquentQuery()`): `OwnerUiScope::apply(Variant::query())`, eager-loads `experiment:id,name`.

**Columns**: experiment name, code (badge), name, traffic_percentage (suffixed %), is_control (boolean icon), is_active (boolean icon), position.

**Filters**: is_active TernaryFilter.

### 5.2 Create

`CreateVariant` uses `VariantForm::configure()`.

**Form sections**:
1. **Variant**: experiment_id (owner-scoped, disabled on edit, live-updates settings), code (scoped-unique per experiment), name, traffic_percentage (1-100), position, is_control, is_active, description.
2. **Variant Settings**: conditionally visible per parent experiment's module_type.

**Code scoped uniqueness**: `scopeCodeUniquenessToExperiment()` resolves experiment via owner-scoped query, scopes to `where('experiment_id', ...)`.

**Settings normalization**: When `experiment_id` changes (live), strips any settings keys not in the allowed set for the resolved module type. Module type cached per-request via `request()->attributes` with owner-scoped cache key.

### 5.3 Edit

Same form with `experiment_id` disabled. `canEdit()` checks direct owner mutability first, then delegates to parent experiment's editability via tracked property.

### 5.4 Delete

Same owner-guarded pattern. `canDeleteAnyVariant()` checks `OwnerUiScope::canCreate(Variant::class)` AND that at least one Variant exists.

## 6. Page & Widget Lifecycle

### 6.1 Growth Dashboard

`GrowthDashboard` extends Filament's `Dashboard`. Widgets (conditional on `features.widgets`):
1. `GrowthStatsWidget` — 4 stat cards (Active Experiments, Variants, Assignments, Tracked Revenue).
2. `ExperimentWinnersWidget` — Blade view showing 5 most recently updated experiments with winner snapshots.

**Revenue formatting**: Uses `FormatsMoney` trait, default currency from `config('signals.defaults.currency', 'MYR')`. Mixed currencies display as "Mixed" with breakdown.

### 6.2 Experiment Results Page

**Mount**: Checks `canAccess()`, reads `experiment` from query string, defaults `chartMetric` to `revenue_per_visitor`.

**Form schema**: `experimentId` select (owner-scoped), `chartMetric` select (revenue_per_visitor, revenue_minor, conversion_rate, checkout_starts, purchases).

**Results computation**: `selectedExperiment()` resolves experiment with cross-package owner scope via `OwnerUiScope::applyForRecordOwner()`. `getResults()` calls `AggregateExperimentMetrics::handle()` (returns empty array on failure).

**Header actions**: "Manage Experiments" (links to ExperimentResource), "Refresh".

### 6.3 Manage Growth Settings

Manages `GrowthSettings` (spatie/laravel-settings). Single `Toggle` for `experimentMiddlewareEnabled`. Gated by `viewAny` on Experiment.

### 6.4 Widget Visibility

Both `GrowthStatsWidget` and `ExperimentWinnersWidget` independently check auth + `canView()` + `Gate::allows('viewAny', Experiment::class)`.

## 7. Owner Scoping & Security

### 7.1 Policy registration

At `packageBooted()`: `Gate::policy(Experiment::class, ExperimentPolicy::class)` and `Gate::policy(Variant::class, VariantPolicy::class)`.

### 7.2 ExperimentPolicy

| Ability | Logic |
| --- | --- |
| `viewAny` | Checks TrackedProperty existence in scope, or global Experiment existence |
| `view` | `OwnerUiScope::canAccessRecord($experiment)` |
| `create` | Owner-scoped create check + TrackedProperty existence |
| `update` / `delete` | `OwnerUiScope::canMutateRecord($experiment)` |

### 7.3 VariantPolicy

| Ability | Logic |
| --- | --- |
| `viewAny` | Checks Experiment or TrackedProperty existence in scope |
| `view` | `OwnerUiScope::canAccessRecord($variant)` |
| `create` | Variant create check + Experiment/TrackedProperty existence |
| `update` / `delete` | `OwnerUiScope::canMutateRecord($variant)` |

### 7.4 Dual mutability for experiments

`canEdit()` and `canDelete()` accept two paths:
1. **Direct**: `OwnerUiScope::canMutateRecord($record)` — user's owner matches experiment's owner tuple.
2. **Indirect via TrackedProperty**: `ExperimentHelpers::canMutateViaTrackedProperty($experiment)` — parent TrackedProperty is accessible.

### 7.5 Cross-package owner scope matching

`ExperimentsTable::findTrackedPropertyForExperiment()` and `ExperimentResultsPage::findTrackedPropertyForExperiment()` use `OwnerUiScope::applyForRecordOwner()` with separate config keys for record's owner config (`growth.features.owner`) and query's owner config (`signals.owner`).

### 7.6 UI vs server enforcement

All relationship selects (`trackedProperty`, `experiment`) apply `OwnerUiScope` to dropdown options, but server-side policies remain the authoritative gate — form options are a UX convenience, not a security boundary.
