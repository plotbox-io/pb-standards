## JavaScript Standards and Best Practices

Applies to all JavaScript across every Plotbox repo regardless of framework.
These are the expected standards for **new code**. Legacy code is tolerated but
should be improved when touched.

### Core Philosophy
- Optimise for the reader, not the writer.
- Explicit over implicit. Name things clearly.
- Fail loudly — surface errors early rather than degrading silently.

### Naming
- `camelCase` for variables and functions; `PascalCase` for classes and Vue components.
- Booleans prefixed with `is`, `has`, `should`, or `can` (`isLoading`, `hasError`).
- Functions named with verb phrases (`fetchOrders`, `buildPayload`, `validateEmail`).
- Event handlers prefixed with `on` or `handle` (`onSubmit`, `handlePageChange`).
- **No abbreviations.** Write `event`, `element`, `response`, `request`, `error`,
  `callback` — not `e`, `el`, `res`, `req`, `err`, `cb`. Exceptions: `id`, `url`, `i`.

### Module Structure
- One concept per module. A module covering several unrelated concerns should be split.
- **Prefer named exports.** Default exports are acceptable only for Vue components and
  entry-point files.
- Group imports: external libraries first, then internal modules, blank line between.

### Guard Clauses and Defensive Programming
Validate inputs at the top of every function and return or throw early. Defects should
surface immediately, not be buried in nested conditions.

```js
// ❌ Nested — happy path buried, defects hidden
function processOrder(order) {
    if (order) {
        if (order.items.length > 0) {
            if (order.isPaid) { return dispatch(order); }
        }
    }
}

// ✅ Guard clauses — defects caught first, happy path obvious
function processOrder(order) {
    if (!order) throw new Error('order is required');
    if (order.items.length === 0) return;
    if (!order.isPaid) return;
    return dispatch(order);
}
```

### Functions
- Single responsibility. If you need "and" to describe a function, split it.
- Aim for under 30 lines. Significantly longer warrants decomposition.
- Prefer pure functions — inputs in, output out, no hidden side effects.
- **Never mutate function arguments.** Return a new value.

```js
// ❌ Mutates the argument
function addTotal(order) { order.total = order.items.reduce(...); return order; }

// ✅ Returns new object
function addTotal(order) { return { ...order, total: order.items.reduce(...) }; }
```

- Extract numeric literals with meaning into named constants — no magic numbers.

```js
const MINIMUM_PASSWORD_LENGTH = 8;
if (password.length < MINIMUM_PASSWORD_LENGTH) { ... }
```

### Variables
- Always `const`; use `let` only when reassignment is needed. Never `var`.
- Prefer destructuring where it aids clarity, not where it obscures it.
- Use template literals, not string concatenation.

### Strict Equality
Always `===` and `!==`. Never `==` or `!=`.

### Async / Await
- **Prefer `async`/`await` over `.then()` chaining.** It reads linearly and keeps
  error handling in one place.
- Do not mix both styles in the same function.
- Never fire-and-forget without handling rejection.
- Only mark a function `async` if it actually uses `await`.

### Error Handling
- Throw `Error` objects — never strings or plain objects.
- Let errors propagate to the nearest appropriate boundary. Do not catch inline just
  to silence them.
- **No empty `catch` blocks.** Either rethrow with context or surface the error visibly.

```js
// ❌ Silently swallowed
try { await submitPayment(payload); } catch (error) {}

// ✅ Rethrows with context
try {
    await submitPayment(payload);
} catch (error) {
    throw new Error(`Payment failed for order ${orderId}: ${error.message}`);
}
```

### Immutability
- `const` by default. Treat data passed into a function as read-only.
- Prefer `map`, `filter`, `reduce`, spread (`...`) for building new structures.
- Mutating methods (`push`, `splice`, direct assignment) are fine within local scope
  only — never on data passed in from outside.

### Comments and JSDoc
- Comments explain **why**, not what. No narrating the code.
- No commented-out code in commits.
- **Exported functions in utility modules and composables must have JSDoc types**
  (in non-TypeScript repos). Internal component functions: recommended when non-obvious.

```js
/**
 * @param {Date} date
 * @param {string} [locale='en-GB']
 * @returns {string}
 */
export function formatDate(date, locale = 'en-GB') { ... }
```

When TypeScript is adopted, type signatures replace JSDoc — do not keep both.

### Anti-patterns
- `var` — use `const` or `let`.
- `==` / `!=` — use `===` / `!==`.
- `console.log` left in committed code.
- Mutating function arguments.
- Empty `catch` blocks.
- Mixing `async`/`await` and `.then()` in the same function.
- Default exports on utility modules.
- Abbreviations in identifiers.
- Nesting deeper than 3 levels — use guard clauses or extract.
- Large functions without clear reason — extract sub-concerns.
- **Boolean trap** — positional booleans are unreadable at call sites. Use named options.
  ```js
  // ❌  createRecord(id, true, false, true)
  // ✅  createRecord(id, { isDraft: true, sendEmail: false, notifyAdmin: true })
  ```
- **Truthy/falsy on values where `0` or `""` are valid** — `if (count)` treats `0` as
  falsy. Be explicit: `if (count > 0)`.
- **`for...in` on arrays** — use `for...of` or array methods instead.
- **Side effects inside `map`/`filter`** — `map` transforms and returns; use `forEach`
  for side effects.
- **Nested ternaries** — one level is fine; more than one, use `if/else`.
- **Extending native prototypes** — never add to `Array.prototype`, `String.prototype`, etc.
- **`parseInt` without radix** — always `parseInt(str, 10)`.
