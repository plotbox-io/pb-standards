## TypeScript Standards and Best Practices

All rules in `JAVASCRIPT.md` apply to TypeScript code. This file covers only the
TypeScript-specific additions. When a repo adopts TypeScript, include both the
`JAVASCRIPT` and `TYPESCRIPT` modules in the compiled AI guidance.

---

### Compiler Configuration

- Enable `strict: true` in `tsconfig.json`. This activates the full set of strict
  checks including `strictNullChecks`, `noImplicitAny`, and `strictFunctionTypes`.
  Do not disable individual strict flags to work around type errors — fix the types.
- Set `noUncheckedIndexedAccess: true` where possible. Accessing an array element or
  record value by index should acknowledge the possibility of `undefined`.

---

### Types and Annotations

- **Prefer type inference** for local variables where the type is obvious from the
  assignment. Explicit annotations add noise where the compiler already knows the type.

```ts
// ❌ Redundant — compiler already infers string
const name: string = 'Alice';

// ✅ Inferred
const name = 'Alice';

// ✅ Annotate where inference isn't clear or the type is the contract
function buildPayload(order: Order): PaymentPayload { ... }
```

- **Annotate all exported function parameters and return types.** When TypeScript is
  adopted, these replace JSDoc type annotations entirely — do not duplicate both.
- **Use `interface` for object shapes** that describe the structure of data or
  implement a contract. **Use `type` for unions, intersections, and aliases.**

```ts
// ✅ Interface for a data shape
interface Order {
    id: number;
    items: OrderItem[];
    isPaid: boolean;
}

// ✅ Type alias for a union
type PaymentStatus = 'pending' | 'paid' | 'failed';
```

---

### Avoiding `any`

- Treat `any` as a code smell. It disables type checking entirely and defeats the
  purpose of using TypeScript.
- If the type is genuinely unknown, use `unknown` — it forces you to narrow before use.
- If you need flexibility across types, use generics.
- The only acceptable uses of `any` are: third-party library types that cannot be
  corrected, and narrow, well-commented escape hatches that cannot be resolved otherwise.

```ts
// ❌ BAD
function processData(data: any) { ... }

// ✅ GOOD — use unknown and narrow
function processData(data: unknown) {
    if (typeof data !== 'object' || data === null) throw new Error('Invalid data');
    ...
}

// ✅ GOOD — use generics for flexibility
function firstItem<T>(items: T[]): T | undefined {
    return items[0];
}
```

---

### Type Narrowing Over Assertions

- Prefer type guards and narrowing over type assertions (`as`). Assertions silence the
  compiler without verifying the actual runtime type.

```ts
// ❌ BAD — asserts without checking
const input = document.getElementById('email') as HTMLInputElement;

// ✅ GOOD — narrows with a guard
const element = document.getElementById('email');
if (!(element instanceof HTMLInputElement)) throw new Error('Email input not found');
const input = element;
```

- Never use the non-null assertion operator (`!`) except as a last resort. Prefer
  explicit null checks or optional chaining (`?.`).

---

### Generics

- Use generics to write flexible, reusable functions and types without falling back
  to `any`.
- Keep generic type parameter names meaningful for non-trivial cases. `T` is fine for
  a simple single-type parameter; `TItem`, `TKey`, `TResult` are better when the
  purpose needs distinguishing.

---

### Enums

- Prefer `const` objects with `as const` over TypeScript `enum` for string-based sets
  of values. `enum` compiles to runtime code and has subtle hoisting and import
  behaviours that cause surprises.

```ts
// ❌ Avoid enum for simple string values
enum Status { Pending = 'pending', Paid = 'paid' }

// ✅ Prefer const object
const STATUS = {
    PENDING: 'pending',
    PAID: 'paid',
} as const;
type Status = typeof STATUS[keyof typeof STATUS];
```

---

### Null and Undefined

- Distinguish between `null` (an explicit absence of value) and `undefined` (a value
  that was never set). Be consistent within a module about which one you use.
- Use optional chaining (`?.`) and nullish coalescing (`??`) rather than verbose
  null/undefined guards where the intent is clear.

```ts
// ✅ Concise and explicit
const city = user?.address?.city ?? 'Unknown';
```

---

### Migration from JavaScript

- When migrating a JS file to TS, fix all type errors rather than suppressing them with
  `// @ts-ignore` or `any`. Suppressions are technical debt that compound over time.
- Remove JSDoc type annotations (`@param {string}`, `@returns {number}`) as you add
  TypeScript type signatures — do not keep both.
- Start with `strict: true` from the first file, even during migration. It is far
  harder to enable later when violations have accumulated.
