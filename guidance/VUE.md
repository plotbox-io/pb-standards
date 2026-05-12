## Vue 3 Standards and Best Practices

### Naming
- Directories: `lowercase-with-dashes`.
- Components: `PascalCase`.
- Composables/utilities: `camelCase`.
- One component per file; colocate small helpers next to their component when scoped to it.

### Structure
- Prefer Composition API with `<script setup>`; avoid Options API in new code.
- File order: imports → props/emit → state (`ref`/`computed`) → watchers → lifecycle → functions → expose → template → styles.
- Keep templates declarative; push logic into composables or computed properties.

### State Management
- Use Pinia for shared state; keep stores small and typed.
- Local state stays local; don't overuse global stores.
- Derive data with `computed`; avoid storing duplicates.

### Composables
- Composables encapsulate reusable logic: inputs as parameters, outputs as a return object.
- Namespace side-effectful composables clearly (e.g. `useFetchCustomer`, `useDebouncedSearch`).
- Do not leak UI concerns from composables.

### Styling & Accessibility
- PrimeVue for components; Tailwind CSS for styling; utility classes over deep custom CSS.
- Mobile-first; use Tailwind responsive utilities.
- Semantic HTML, labelled controls, keyboard navigation, colour-contrast compliance.
- No inline styles; use Tailwind and CSS variables.

### API & Async
- All HTTP calls go in API modules/composables; never call `fetch`/`axios` directly in components.
- Handle loading, error, and empty states explicitly.
- Use abortable requests for fast navigation; debounce where appropriate.
- Validate and normalise API data at the boundary.

### Performance
- Keep components small; use dynamic imports for heavy chunks.
- Avoid unnecessary watchers; prefer `computed` and event-driven updates.

### Events & Communication
- Prefer explicit `emit`/props over global event buses.
- Use `provide/inject` only for cross-cutting concerns (themes, i18n), not arbitrary state.

### Testing
- Assert behaviour (rendered output/events), not internals.
- Extract testable logic into composables; test with Vitest following `JAVASCRIPT.md`.

### Tooling
- Vite for builds; ESLint/Prettier with Vue and TypeScript support.
- Keep CI fast: type checks, lint, and tests on changed modules.

### Reviewer Calibration
- **Composable extraction**: Only suggest extracting component logic into a composable when the logic contains multiple branches or is reused across components. Do not suggest extraction for single guards, lone watchers, or mount-time fetches in narrowly-scoped bugfix PRs.
