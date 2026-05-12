## JavaScript Standards and Best Practices

Vue-specific patterns live in `VUE.md`; general principles in `GENERAL.md`.

### Testing

#### Framework
- Use Vitest for all JS unit tests. Mock network and timers; tests must be deterministic.
- Test composable-level and pure functions only. Vue templates are not testable directly — extract logic into a composable if it needs a test.

#### File Layout
- Mirror the source path: `src/composables/foo.js` → `src/tests/composables/foo.spec.js`.
- One test file per module; one `describe` block per exported function.

#### Naming
- `describe` block named after the function under test.
- `it` / `test` uses `should_*` in `snake_case`.

#### Test Body Structure
- Test bodies contain **only** flat `given_*`, `when_*`, `then_*` calls — no inline setup, assertions, or control flow.
- Helper functions at **module scope** (after `describe` blocks), never nested inside.
- No `async/await` or `.then()` in test bodies; wrap async work in helpers.

#### State & Setup
- Shared state via module-scoped `let`, reset in `beforeEach`. Tests must be independent.

#### Example

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

### Tooling
- ESLint with strict config; fix all warnings before committing.
- Run lint and tests on changed modules in CI.
