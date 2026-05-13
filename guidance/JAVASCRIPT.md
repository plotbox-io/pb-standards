## JavaScript Standards and Best Practices

Vue-specific patterns live in `VUE.md`; Vitest testing conventions live in `VITEST.md`;
TypeScript-specific additions live in `TYPESCRIPT.md`; general principles in `GENERAL.md`.

These rules apply to all JavaScript across every Plotbox repo regardless of framework.
They represent the expected standard for **new code**. Legacy code that pre-dates these
rules is tolerated but should be improved when touched.

---

### Core Philosophy

- Code is read far more than it is written. Optimise for the reader.
- Explicit over implicit. Name things clearly; don't rely on context that isn't obvious.
- Fail loudly. Surface errors early rather than swallowing them and degrading silently.

---

### Naming

- Use `camelCase` for variables and functions; `PascalCase` for classes, constructors,
  and Vue component names.
- Boolean variables and props should be prefixed with `is`, `has`, `should`, or `can`
  (e.g. `isLoading`, `hasError`, `canSubmit`).
- Functions should be named with verb phrases that describe what they do
  (e.g. `fetchOrders`, `buildPayload`, `validateEmail`).
- Event handlers should be prefixed with `on` or `handle`
  (e.g. `onSubmit`, `handlePageChange`).
- **No abbreviations** — this rule is the same as for PHP. Do not use `e`, `el`, `cb`,
  `res`, `req`, `err`, `val`, `arr`, `obj`, `fn`, or similar shorthand. Write `event`,
  `element`, `callback`, `response`, `request`, `error`, `value`, `array`, `object`,
  `handler`. Exceptions: `id`, `url`, `i` (loop counter).

---

### Module Structure

- One concept per module. If a module has grown to cover several unrelated concerns,
  split it.
- **Prefer named exports.** Default exports are acceptable only for Vue components and
  application entry-point files. Named exports make refactoring safer and import names
  consistent across the codebase.
- Keep module files reasonably short. A file that is growing very long is a signal that
  it covers too many concerns.
- Group imports: external libraries first, then internal modules, with a blank line
  between groups.

---

### Functions

- **Keep functions small and single-purpose.** A function should do one thing. If you
  need to describe what a function does using "and", it should be two functions.
- As a rough guide, aim for functions under 30 lines. A function that significantly
  exceeds this warrants scrutiny — either it can be decomposed, or it is genuinely
  complex and should be well-commented.
- **Use guard clauses and early returns** to keep the happy path at the lowest indentation
  level. Avoid deeply nested `if/else` trees.

```js
// ❌ BAD — nested, happy path buried
function processOrder(order) {
    if (order) {
        if (order.items.length > 0) {
            if (order.isPaid) {
                return dispatch(order);
            }
        }
    }
}

// ✅ GOOD — guard clauses, happy path obvious
function processOrder(order) {
    if (!order) return;
    if (order.items.length === 0) return;
    if (!order.isPaid) return;
    return dispatch(order);
}
```

- **Prefer pure functions** — functions that take inputs and return outputs without
  modifying external state. Side effects should be pushed to the edges of the system.
- **Do not mutate function arguments.** Return a new value instead of modifying what
  was passed in.

```js
// ❌ BAD — mutates the argument
function addTotal(order) {
    order.total = order.items.reduce((sum, item) => sum + item.price, 0);
    return order;
}

// ✅ GOOD — returns a new object
function addTotal(order) {
    return { ...order, total: order.items.reduce((sum, item) => sum + item.price, 0) };
}
```

- Avoid magic numbers. Extract numeric literals that carry meaning into named constants.

```js
// ❌ BAD
if (password.length < 8) { ... }

// ✅ GOOD
const MINIMUM_PASSWORD_LENGTH = 8;
if (password.length < MINIMUM_PASSWORD_LENGTH) { ... }
```

---

### Variables

- Always use `const` unless the variable will be reassigned, in which case use `let`.
  Never use `var`.
- Prefer destructuring where it makes intent clearer, but do not force it when the
  explicit form reads more naturally.

```js
// ✅ Destructuring that aids clarity
const { id, name, email } = user;

// ✅ Explicit form that also reads well
const userId = response.data.id;
```

- Use template literals instead of string concatenation.

```js
// ❌ BAD
const message = 'Hello, ' + name + '. You have ' + count + ' messages.';

// ✅ GOOD
const message = `Hello, ${name}. You have ${count} messages.`;
```

---

### Strict Equality

Always use `===` and `!==`. Never use `==` or `!=`. Implicit type coercion produces
surprising results and is a common source of bugs.

---

### Async / Await

- **Prefer `async`/`await` over `.then()` chaining** for all new code. `async`/`await`
  reads linearly, nests less, and makes error handling with `try`/`catch` straightforward.
- Do not mix `async`/`await` and `.then()` in the same function or module without a
  clear reason.
- Never fire-and-forget an async call without handling the result or potential rejection.

```js
// ❌ BAD — chained, error handling spread across callbacks
fetchOrder(id)
    .then(order => processOrder(order))
    .then(result => dispatch(result))
    .catch(error => console.error(error));

// ✅ GOOD — linear, error handling in one place
async function submitOrder(id) {
    const order = await fetchOrder(id);
    const result = await processOrder(order);
    return dispatch(result);
}
```

- Mark a function `async` only if it actually uses `await`. A function that returns a
  resolved promise via `return value` does not need to be `async`.

---

### Error Handling

- Always throw `Error` objects (or subclasses), never strings or plain objects.
- Let errors propagate to the nearest appropriate boundary. Do not catch errors inline
  just to silence them.
- Never write an empty `catch` block. If a `catch` is genuinely needed, either rethrow
  with added context or surface the error visibly to the user.

```js
// ❌ BAD — silently swallows the error
try {
    await submitPayment(payload);
} catch (error) {}

// ✅ GOOD — rethrows with context
try {
    await submitPayment(payload);
} catch (error) {
    throw new Error(`Payment submission failed for order ${orderId}: ${error.message}`);
}
```

- Catch the narrowest exception scope possible. Do not wrap large blocks in a single
  `try/catch` when only one specific call can fail.

---

### Immutability

- Treat data passed into a function as read-only. Return new structures rather than
  modifying arguments.
- Prefer `map`, `filter`, `reduce`, and spread (`...`) over mutating array/object
  methods when building new values from existing ones.
- `push`, `pop`, `splice`, and direct property assignment are acceptable within a local
  scope but must not be used on data passed in from the outside.

---

### Comments

- Same rule as PHP: comments explain **why**, not what. Do not narrate the code.
- Do not commit commented-out code. Use version control to recover old code.
- **JSDoc types on exported functions** (non-TypeScript repos): all exported functions
  in utility modules and composables must document their parameter types and return type
  with JSDoc. This provides IDE support and creates a clear migration target for when
  TypeScript is adopted.

```js
/**
 * Formats a date for display in the UI.
 *
 * @param {Date} date
 * @param {string} [locale='en-GB']
 * @returns {string}
 */
export function formatDate(date, locale = 'en-GB') {
    return date.toLocaleDateString(locale);
}
```

- JSDoc types on **internal component functions** are recommended when the types are
  not obvious from context, but not required. If an internal function is complex enough
  that its types need documenting, consider whether it should be extracted to a utility
  module.
- When TypeScript is adopted, JSDoc type annotations are superseded by type signatures
  and should be removed in favour of them.

---

### Anti-patterns to Avoid

- `var` — always `const` or `let`.
- `==` / `!=` — always `===` / `!==`.
- `console.log` left in committed code.
- Mutating function arguments.
- Empty `catch` blocks.
- Mixing `async`/`await` and `.then()` in the same function.
- Default exports on utility modules (named exports only).
- Abbreviations in identifiers (see Naming section).
- Deep nesting — more than 3 levels of indentation is a signal to extract or use guard
  clauses.
- Functions that grow very long without a clear reason — extract sub-concerns.
- **Boolean trap** — positional boolean arguments make call sites unreadable. Use a
  named options object instead.

```js
// ❌ BAD — what do these booleans mean?
createRecord(id, true, false, true);

// ✅ GOOD — intent is explicit
createRecord(id, { isDraft: true, sendEmail: false, notifyAdmin: true });
```

- **Truthy/falsy on values where `0` or `""` are meaningful** — `if (count)` silently
  treats `0` as falsy. Be explicit: `if (count > 0)` or `if (count !== null)`.
- **`for...in` on arrays** — this iterates prototype properties too. Always use `for...of`
  or array methods (`map`, `filter`, `forEach`) when iterating arrays.
- **Side effects inside `map` or `filter`** — `map` should transform and return, never
  mutate. If you are using `map` for side effects and discarding the result, use
  `forEach` instead.
- **Deeply nested ternaries** — a single ternary is fine; more than one level deep
  becomes unreadable. Use `if/else` or extract to a named function.
- **Extending native prototypes** — never add methods to `Array.prototype`,
  `String.prototype`, `Object.prototype`, etc. It pollutes the global scope and causes
  conflicts with future language features.
- **`parseInt` without radix** — always pass the radix explicitly: `parseInt(str, 10)`.
  Without it, strings prefixed with `0x` or `0` are interpreted as hex or octal.

---

### Tooling

- ESLint with strict config; fix all warnings before committing.
- Run lint and tests on changed modules in CI.
- Use Vitest for all JS unit tests. See `VITEST.md` for testing conventions, file layout,
  naming, and what is and is not in scope for unit tests.
- When adopting TypeScript, see `TYPESCRIPT.md` for additional standards.
