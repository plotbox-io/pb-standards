## TypeScript Standards and Best Practices

All general JavaScript standards apply. These are TypeScript-specific additions only.

### Compiler Configuration
- `strict: true` in `tsconfig.json` — do not disable individual strict flags to work
  around errors, fix the types.
- Enable `noUncheckedIndexedAccess` where possible.

### Type Annotations
- **Prefer inference** for local variables — annotate where the type isn't obvious or
  is a public contract.
- **Annotate all exported function parameters and return types.** These replace JSDoc
  type annotations — do not keep both.
- **`interface` for object shapes; `type` for unions, intersections, and aliases.**

```ts
interface Order { id: number; items: OrderItem[]; isPaid: boolean; }
type PaymentStatus = 'pending' | 'paid' | 'failed';
```

### Avoiding `any`
Treat `any` as a code smell — it disables type checking entirely.
- Use `unknown` when the type is genuinely unknown (forces narrowing before use).
- Use generics for flexibility across types.
- `any` is acceptable only for uncorrectable third-party types or narrow, justified
  escape hatches with an explanatory comment.

```ts
// ❌ function processData(data: any) { ... }

// ✅ Use unknown and narrow
function processData(data: unknown) {
    if (typeof data !== 'object' || data === null) throw new Error('Invalid data');
}

// ✅ Use generics for reusable flexibility
function firstItem<T>(items: T[]): T | undefined { return items[0]; }
```

### Type Narrowing Over Assertions
- Prefer type guards over `as` assertions — assertions silence the compiler without
  verifying the runtime type.
- Avoid the non-null assertion operator (`!`); prefer explicit null checks or `?.`.

```ts
// ❌ const input = document.getElementById('email') as HTMLInputElement;

// ✅ Narrow with a guard
const element = document.getElementById('email');
if (!(element instanceof HTMLInputElement)) throw new Error('Email input not found');
```

### Enums
Prefer `const` objects with `as const` over `enum`. TypeScript enums compile to
runtime code with subtle hoisting and import behaviours.

```ts
// ❌ enum Status { Pending = 'pending', Paid = 'paid' }

// ✅
const STATUS = { PENDING: 'pending', PAID: 'paid' } as const;
type Status = typeof STATUS[keyof typeof STATUS];
```

### Null and Undefined
- Be consistent within a module about which you use.
- Use `?.` and `??` rather than verbose null guards where intent is clear.

### Migration from JavaScript
- Fix type errors rather than suppressing with `// @ts-ignore` or `any`.
- Remove JSDoc type annotations as you add TypeScript signatures — do not keep both.
- Enable `strict: true` from the very first file; enabling it later is far harder.
