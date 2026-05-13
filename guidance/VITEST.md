## Vitest Testing Standards

These rules govern all JavaScript and TypeScript unit testing using Vitest.
They apply regardless of whether the project uses Vue, React, or any other
UI framework.

### What to Test

Only the following are unit tested with Vitest:

- **Composables** — reusable Vue/JS logic functions (e.g. `useFilters`, `useFetchCustomer`)
- **Utility modules** — pure functions, formatters, data transformers, helpers
- **API modules** — thin `fetch`/`axios` wrappers (assert the URL called, not HTTP internals)

### What NOT to Test

Do not suggest or write Vitest unit tests for:

- **Vue component files (`.vue`)** — template rendering and component integration are not
  unit tested with Vitest. If logic in a component needs testing, extract it into a
  composable or utility module first, then test that.
- **Blade inline scripts** — PHP-rendered inline JavaScript in Blade/HTML templates cannot
  be meaningfully unit tested with Vitest. Do not suggest wrapping Vue setup blocks or
  inline script logic in Vitest tests.
- **Stimulus controllers** — thin orchestrators that wire DOM targets to logic modules.
  Extract any non-trivial logic into a `lib/` module and test that instead.
- **DOM integration** — Vitest runs in a simulated environment; do not assert on rendered
  HTML, DOM state, or visual output.

### Reviewer Calibration — Coverage Comments

When reviewing a PR that adds or modifies Vue components, Blade templates, or
Blade-embedded Vue instances:

- **Do NOT** suggest writing Vitest tests for `.vue` component files directly.
- **Do NOT** suggest adding feature tests or HTML-marker assertions (e.g. checking for
  `v-if` attributes or `data-*` values in rendered HTML) as a proxy for JS unit tests.
- **Do NOT** raise a coverage comment if the JS logic lives in an inline Blade script —
  note that no JS test framework is available for that code path.
- **DO** suggest extracting logic into a composable or utility module when the logic
  contains multiple meaningful branches and is reused across components.
- A single `v-if`, a lone watcher, or a mount-time fetch is not sufficient reason to
  demand extraction or test coverage.

### File Layout

Mirror the source path under the test directory. One test file per module; one `describe`
block per exported function.

| Source | Test |
|---|---|
| `src/composables/useFilters.js` | `src/tests/composables/useFilters.spec.js` |
| `assets/lib/metrics.js` | `tests/js/lib/metrics.test.js` |
| `assets/lib/foo/bar.js` | `tests/js/lib/foo/bar.test.js` |

### Naming

- `describe` block named after the function under test.
- `it` / `test` uses `should_*` in `snake_case`
  (e.g. `it('should_return_empty_array_when_input_is_null', ...)`).

### Test Body Structure

- Test bodies contain **only** flat `given_*`, `when_*`, `then_*` calls — no inline
  setup, assertions, or control flow.
- Helper functions live at **module scope** (after `describe` blocks), never nested inside
  `describe` or `it`.
- No `async/await` or `.then()` in test bodies; wrap async work inside helpers.
- Shared state via module-scoped `let` variables, reset in `beforeEach`. Tests must be
  independent.

### Example

```js
import { describe, it, expect, beforeEach } from 'vitest';
import { useFilters } from '@/composables/filters';

let result;

describe('applyDefaults', () => {
    beforeEach(() => { result = undefined; });

    it('should_use_empty_array_when_value_is_null', () => {
        given_null_input();
        when_defaults_applied();
        then_result_is_empty_array();
    });
});

function given_null_input() { /* setup */ }
function when_defaults_applied() { result = useFilters().applyDefaults(null); }
function then_result_is_empty_array() { expect(result).toEqual([]); }
```

### Testing Async API Modules

API modules (thin `fetch` wrappers) are tested by stubbing `global.fetch` and asserting
the URL called. Since `fetch` is invoked synchronously, no `await` is needed:

```js
function when_coverage_is_fetched() {
    stubFetch();
    fetchCoverage(filters);
}

function then_fetch_was_called_with(url) {
    expect(global.fetch).toHaveBeenCalledWith(url);
}
```
