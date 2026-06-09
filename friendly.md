## Second pass — 2026-06-09

### Confirmed

- **Phase 1 — replace ad-hoc owner-scope helper**: `AccessibleGrowthRecords.php` deleted. Only `ExperimentHelpers.php` remains in `Support/`, using `OwnerTupleColumns` from commerce-support for owner-matched child counting.
- **Phase 2 — split resources into subfolders**: Both `ExperimentResource/Schemas/` and `VariantResource/Schemas/` exist with form files. Both also have `Tables/` subfolders.
- **Phase 3 — consolidate getEloquentQuery**: Both resources have single, non-stacked overrides. `ExperimentResource` delegates to `ExperimentHelpers::applyOwnerSafeRelationCounts()`. `VariantResource` calls `Variant::query()->with()` directly.

### Still open

None — all checklists marked [done].

### New findings

1. **`ExperimentResource::getEloquentQuery()` uses `Experiment::query()` directly, not `parent::getEloquentQuery()`.** This means the query chain does not go through Filament's built-in tenancy. The `Experiment` model presumably has `HasOwner` (global scope), so ownership scoping is still applied, but it's implicit. Compare with `EventResource` which calls `parent::getEloquentQuery()` then applies `OwnerUiScope`.

2. **`VariantResource::getEloquentQuery()` has no owner-scope application at all.** Uses `Variant::query()->with(...)` with no `OwnerUiScope::apply()` or `parent::getEloquentQuery()` call. Relies entirely on the model's global scope — if the `HasOwner` trait is ever removed from `Variant`, this query will leak data.

3. **`ExperimentHelpers::ownerMatchedChildCount()` bypasses `OwnerScope` manually.** Uses `withoutGlobalScope(OwnerScope::class)` then hand-rolls owner matching via `whereColumn` comparisons. This is intentional (subquery counting where parent/child owner tuples must match), but the pattern is fragile and not documented.

4. **`GrowthStatsWidget` has inline query logic.** The widget (157 lines) performs `Experiment::query()` with raw `selectRaw`/`sum` aggregations and loops through experiments for revenue. The original finding 4 recommended extracting to a `Support/GrowthStatsAggregator` — this was not done.

5. **`canDeleteAny()` returns `true` unconditionally** in `ExperimentResource.php:98`. No owner check on bulk delete permission.

### Updated recommendation

Priority 1: Apply `OwnerUiScope` (or explicit parent call) consistently to `VariantResource::getEloquentQuery()` and document the implicit-scope pattern in `ExperimentResource`. Priority 2: Extract `GrowthStatsWidget` query logic to a support class. Priority 3: Gate `canDeleteAny()` behind owner/permission check.

---

# Filament Growth friendliness review

This note reviews `packages/filament-growth` against two repo-level expectations:

- when a capability may grow variants, prefer stable seams such as contracts, metadata, hooks, domain events, resolvers, and support classes
- when orchestration repeats, extract reusable Actions, Services, or Use Cases so the package stays friendly to multiple entrypoints

## What I reviewed

- `src/Resources` (2)
- `src/Pages` (3)
- `src/Widgets` (2)
- `src/Support/AccessibleGrowthRecords.php` (15 query hits, uses `withoutOwnerScope`)
- `src/Policies` (2)
- `FilamentGrowthPlugin.php`
- downstream in `growth`, `signals`, `affiliates`, `cart`

## What is already friendly

### Policies cover all resources

- `Policies/ExperimentPolicy.php`
- `Policies/VariantPolicy.php`

This is the only package in the audit set with policies for every resource.

### Plugin is the entry point

- `FilamentGrowthPlugin.php`

Standard shape.

## Findings

### 1. `Support/AccessibleGrowthRecords.php` is the classic ad-hoc owner-scope replacement

**Files**

- `src/Support/AccessibleGrowthRecords.php` (15 query calls, uses `withoutOwnerScope`)

**Why this hurts friendliness**

This is the exact smell the multitenancy guideline warns against: an ad-hoc "accessible" filter that replaces the standard `OwnerScope`. The class is also imported and used heavily in `ExperimentResource`.

**Recommendation**

Replace with `commerce-support`'s `OwnerQuery` and `OwnerScope`. The "accessible" semantics should map to owner scope, not a custom class.

### 2. `ExperimentResource` has 3 `getEloquentQuery` refs

**Files**

- `ExperimentResource`

**Why this hurts friendliness**

3 refs to the same method suggest stacked overrides (superclass + trait + class). Each may add its own filter.

**Recommendation**

Audit the call chain. Consolidate to one.

### 3. No `Schemas/` or `Tables/` subfolders in any resource

**Files**

- `ExperimentResource` and `VariantResource` are bare `Pages` only.

**Why this hurts friendliness**

The standard layout is missing.

**Recommendation**

Split into subfolders following the standard pattern.

### 4. `GrowthStatsWidget` has 4 query calls

**Files**

- `Widgets/GrowthStatsWidget.php`

**Why this hurts friendliness**

4 raw queries in a single widget.

**Recommendation**

Move to a `Support/GrowthStatsAggregator.php` service in the `growth` domain.

### 5. `ManageGrowthSettings` page is settings-as-Page

**Files**

- `src/Pages/ManageGrowthSettings.php`

**Why this hurts friendliness**

Settings UIs typically warrant a dedicated settings pattern. If it's just a CRUD on `GrowthSettings`, use a settings page pattern.

**Recommendation**

Audit the page. If it's a settings UI, document the pattern. If it's a CRUD, consider replacing with a Resource.

## Concrete refactor plan

### Phase 1 — replace ad-hoc owner-scope helper

**Steps**

1. Replace `Support/AccessibleGrowthRecords.php` with `commerce-support`'s `OwnerQuery`.
2. Delete the local helper.
3. Update `ExperimentResource` to use the new pattern.

### Phase 2 — split resources into subfolders

**Steps**

1. Add `Schemas/` and `Tables/` to both resources.

### Phase 3 — consolidate `getEloquentQuery` overrides

**Steps**

1. Audit the call chain.
2. Consolidate.





## Refactor tracking

This checklist tracks progress on the refactor plan above. Each item lists a concrete phase/step.
Agents: claim an item by updating its status. Use `@agent-name` to claim ownership.

Status legend:
- `[pending]` — not started
- `[in-progress]` — being worked on
- `[done]` — completed and verified
- `[blocked]` — blocked by another item

### Phase 1 — replace ad-hoc owner-scope helper

- [done] Replace `Support/AccessibleGrowthRecords.php` with `commerce-support`'s `OwnerQuery`.
- [done] Delete the local helper.
- [done] Update `ExperimentResource` to use the new pattern.

### Phase 2 — split resources into subfolders

- [done] Add `Schemas/` and `Tables/` to both resources.

### Phase 3 — consolidate `getEloquentQuery` overrides

- [done] Audit the call chain. (2 resources: ExperimentResource has 1 override using `ExperimentHelpers::applyOwnerSafeRelationCounts()`; VariantResource has 1 override using `Variant::query()->with()`. Both are single, non-stacked overrides.)
- [done] Consolidate. (Both resources already have single overrides. No stacked pattern exists.)

### Phase 4 — apply owner-scoping consistently across resources

- [done] Apply `OwnerUiScope::apply()` to `VariantResource::getEloquentQuery()` (previously relied solely on model global scope; now explicitly applies `OwnerUiScope::apply()` with `includeGlobal: false`).
- [done] Document that `ExperimentResource::getEloquentQuery()` relies on implicit scope via `HasOwner` global scope on the `Experiment` model (it uses `Experiment::query()` directly, not `parent::getEloquentQuery()`).
- [done] Document and add tests for `ExperimentHelpers::ownerMatchedChildCount()` — the `withoutGlobalScope(OwnerScope::class)` + manual `whereColumn` owner-matching pattern is fragile. Added PHPDoc to `ownerMatchedChildCount()` explaining the intent: subquery counting where parent/child owner tuples must match (same owner or both global). The pattern is intentional: the subquery runs in SQL context where Eloquent's global scope doesn't apply, so `withoutGlobalScope` is required to prevent double-scoping. The manual `whereColumn` match is the correct approach for correlated subquery owner matching.

### Phase 5 — extract GrowthStatsWidget query logic

- [done] Extract query/aggregation logic from `GrowthStatsWidget.php` (157 lines) into `Support/GrowthStatsAggregator` service class.
- [done] Update `GrowthStatsWidget` to delegate to `GrowthStatsAggregator`.

### Phase 6 — gate canDeleteAny behind owner/permission check

- [done] Replace `canDeleteAny()` returning `true` unconditionally in `ExperimentResource.php:98` with `Gate::allows('deleteAny', Experiment::class)` — delegates to `ExperimentPolicy::deleteAny()`.
- [done] Apply same owner gating to `VariantResource::canDeleteAny()` — delegates to `VariantPolicy::deleteAny()`.



## Suggested verification scope

- per-Resource tests
- per-Page tests
- Widget tests
- cross-package tests for growth/signals/affiliates

## Recommended first move

Phase 1 — replace the ad-hoc owner-scope helper. This is the most visible architectural smell and the fix aligns the package with the multitenancy guideline.
