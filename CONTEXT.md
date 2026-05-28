---
title: Filament Growth Context
package: filament-growth
status: current
surface: filament
family: growth-and-incentives
---

# Filament Growth Context

## Snapshot
- Composer: `aiarmada/filament-growth`
- Role: Filament admin UI for experiments, variants, dashboards, and results.
- Search first: `src/Resources`, `src/Pages`, `src/Widgets`, `src/Actions`, `config`, `docs`
- Related: `growth`, `signals`, `filament-signals`

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../growth/CONTEXT.md` when domain behavior or persistence changes are involved
6. `docs/02-installation.md` when plugin or panel setup changes are involved

## Guardrails
- Owns Filament resources, pages, widgets, tables, forms, and panel/plugin glue.
- Keep experiment logic, persistence, and winner calculations in `growth`.
- Revalidate submitted IDs server-side; UI scoping is not authorization.
