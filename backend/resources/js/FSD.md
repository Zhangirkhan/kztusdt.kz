# Frontend FSD structure

Inertia pages stay in `Pages/` (Laravel convention). Business logic lives in FSD layers:

```
resources/js/
  Pages/          # page layer — thin Inertia entry points
  widgets/        # composite UI blocks (layouts, panels, flows)
  features/       # user actions (forms, filters, logout)
  entities/       # domain models, constants, presenters
  shared/         # ui kit, lib, config
  Layouts/        # legacy re-exports → widgets
  Components/     # legacy re-exports → shared (migration in progress)
```

## Import rules

- `Pages` → `widgets`, `features`, `shared`
- `widgets` → `features`, `entities`, `shared`
- `features` → `entities`, `shared`
- `entities` → `shared` only
- No imports from `Pages` into lower layers

## Aliases

- `@/` — project root
- `@shared/` — shared layer
- `@entities/` — entities layer
- `@features/` — features layer
- `@widgets/` — widgets layer
