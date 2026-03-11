## JavaScript Standards and Best Practices

These rules cover JavaScript code quality, testing, and tooling. Vue-specific
patterns live in `VUE.md`; general principles live in `GENERAL.md`.

### Testing

#### Framework
- Use Vitest for all JavaScript unit tests.
- Mock network and timers; make tests deterministic.
- Only composable-level and pure-function tests are supported. Vue template
  testing is not supported due to mocking complexity. If logic needs testing,
  extract it into a composable or utility module.

#### File Layout
- Test file mirrors the source path:
  `src/composables/foo.js` → `src/tests/composables/foo.spec.js`
- One test file per module; one `describe` block per exported function.

#### Naming
- `describe` block named after the function under test.
- `it` / `test` uses `should_*` in `snake_case`
  (e.g., `it('should_return_empty_array_when_input_is_null', ...)`).

#### Test Body Structure
- Test bodies should contain **only** flat `given_*`, `when_*`, `then_*` helper
  calls — no inline setup, assertions, or control flow.
- Helper functions live at **module scope** (after `describe` blocks), never
  nested inside `describe` or `it`.
- No `async/await` or `.then()` in test bodies; wrap async work inside helpers.

#### State & Setup
- Shared state via module-scoped `let` variables, reset in `beforeEach`.
- Each test should be independent; do not rely on execution order.

#### Example

```js
import { describe, it, expect, beforeEach } from 'vitest';
import { useFilters } from '@/composables/filters';

let result;

describe('applyDefaults', () => {
    beforeEach(() => {
        result = undefined;
    });

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

### Tooling
- Use ESLint with strict config; fix all warnings before committing.
- Keep CI fast: run lint and tests on changed modules.
