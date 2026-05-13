## PHP Coding Standards and Best Practices

Rules cover pure PHP. Framework-specific guidance is in the Laravel and Symfony documents.

### Language Level
- Use the latest PHP features for the project runtime.
- Always `declare(strict_types=1)` in new files; be cautious when adding to legacy files.
- Prefer `thecodingmachine/safe` equivalents over native functions that silently return false on failure.

### Types & Contracts
- Use native type hints for all parameters and return types.
- Prefer `?string` over `string|null` for simple nullable types.
- Use `self`/`static` return types for fluent APIs or builders.
- Prefer value objects and enums over string/int primitives when representing a domain concept.
- Document array/iterable generics with PHPDoc (e.g. `array<int, User>`, `list<Order>`).
- Do not use legacy iterable types like `int[]`, `string[]` — they don't convey structure or type safety.
- Do not add `/** @var list<string> */` above constant arrays — redundant noise.
- Prefer a DTO over an array shape at 3+ properties; at 4+ a DTO is required.

### Object Design
- Prefer composition over inheritance. Classes are `final` by default.
- Type-hint against interfaces, not concrete classes — constructor params, method params, properties, and return types.
- Use constructor injection. Avoid service locators and globals.
- Avoid static methods for behaviour; exceptions: named constructors, pure factories, constants, stateless helpers.
- Properties and methods are `private` by default; `protected` only when there is a concrete inheritance need.
- Avoid traits; favour small dedicated classes.
- DTOs may be `readonly` at the class level; avoid per-property `readonly`.

### Code Style
- Use `use` statements; avoid FQCNs inline.
- Strict comparisons (`===`, `!==`).
- Single quotes unless interpolation requires double quotes.
- Short array syntax `[]`; no trailing comma after the last item.
- Use type-safe function variants (e.g. `in_array($v, $arr, true)`).
- Never use `empty()` for null checks; prefer `isset()` or strict comparison.
- Truthy/falsy checks (`if ($value)`, `if (!$value)`) are acceptable and often
  preferred when `null`, `false`, `""`, and `0` should all be treated identically.
  Do not flag these as requiring strict comparison — that would be a misapplication
  of the strict equality rule. Only use `===`/`!==` when the distinction between
  falsy values matters.
- Use `@inheritDoc` on overridden methods rather than duplicating the description.
- Single-tag PHPdocs on one line: `/** @return int */`.
- Do not import global PHP functions with `use function`; only namespaced functions (e.g. `use function Safe\json_decode`).
- Class member order: trait uses → constants → properties → constructor → public → protected → private.
- Constants: `UPPER_SNAKE_CASE`, grouped with no blank lines between them; one blank line separating groups from other members.
- One blank line between methods; no additional whitespace.
- Use constructor property promotion for DTOs.
- Use named arguments when instantiating DTOs with many parameters.
- Repository dependencies named `$thingRepository` (e.g. `$customerRepository`); long names can be shortened sensibly.
- Chained calls on new objects: `$obj = new Foo()->method()` not `$obj = (new Foo())->method()`.
- Explicit casts (e.g. `(int)`) are appropriate when the source value may not match the target type (e.g. request data, DB results). Do not blanket-apply casts for consistency.

### Errors & Exceptions
- Throw domain-specific exceptions with clear, actionable messages; no sensitive data.
- No return codes for errors; do not suppress exceptions.
- Do not add `@throws` for `LogicException`, `RuntimeException`, or `InvalidArgumentException` — these are guard-clause exceptions, not contract exceptions. Only document domain-specific exceptions callers may legitimately catch (e.g. `RecordNotFoundException`).

### Naming
- No abbreviations except well-known ones (`$id`, `$url`, `$html`, `$i`, `$sut`).
- Avoid FQCNs inline; use `use` statements.

### Documentation
- Comments explain *why*, not *what*. Prefer self-explanatory code.
- Use `@inheritDoc` on overridden or implemented methods.

### Repository Design
- Return models (or collections), not primitive IDs or booleans.
- No business logic in repositories — filtering by status, determining privacy, selecting "most recent" belong in handlers/services.
- Use specific, domain-meaningful method names (e.g. `findOrdersForCustomer`), not generic `findByX`.
- Avoid use-case-specific query methods that couple the repository to a single consumer.

### Static Analysis
- Prefer `@var`, `@param`, or `@return` annotations over `@psalm-suppress` when resolving violations.
- Place `@var` where the value is first assigned, not above the violation line.

### Testing & Tooling
- Isolate I/O behind interfaces; inject time/clock, filesystem, and external clients as dependencies.
- Unit tests focus on use-case handlers.
- Prefer fakes over mocks; mock only your own interfaces.
- Test files mirror source structure (e.g. `src/Foo/Bar.php` → `tests/Foo/BarTest.php`).
- One SUT per test class, named `$sut`, constructed only in `setUp()`. Do not reconstruct after `setUp()`.
- Test method names start with `should_`.
- Test bodies contain only calls to `given_`, `when_`, or `then_` methods (except exception expectations).
- `setUp()` and PHPUnit lifecycle methods come before all other methods.
- Data provider methods are public; all other helpers are private and placed after public test methods.
- Model IDs in tests start at 1000 (e.g. 1000, 1001) to avoid collisions with real data.
- Use `@inheritDoc` on overridden base test class methods.
- Use `given_` not continuation methods like `and_`.
- `given_` methods group related setup code under a business-readable name.
- Arguments on `given_` or `then_` methods are permitted when they significantly improve readability; avoid on `when_` methods.
- All test method names and helper names use business language (e.g. `should_send_reminder` not `should_invoke_send_method`).
- All test methods and helpers return `void` with no parameters (except `given_`/`then_` readability exceptions above).
- Internal technical helpers within `given_`/`when_`/`then_` methods may use `camelCase`.
- Implicit `given` preconditions may be omitted or noted with a comment.
- Move `given_` setup common to multiple tests into `setUp()`.

### Reviewer Calibration
Common misapplications to avoid when reviewing PHP code:

- **Explicit casts**: Only flag a missing cast when the source value may not match the target type (e.g. request or DB data). Do not flag casts on already-typed values.
- **`@throws` for guard exceptions**: Never request `@throws LogicException`, `@throws RuntimeException`, or `@throws InvalidArgumentException`. Only flag missing `@throws` for domain exceptions callers may legitimately catch.
- **`@param`/`@return` for simple native types**: Do not suggest adding these when they would only repeat a native type hint already in the signature. Only suggest PHPDoc that adds generics (e.g. `list<User>`) or documents domain exceptions.
- **`given_`/`then_` method parameters**: Do not flag parameters on `given_` or `then_` helpers. They are explicitly permitted when they improve readability.
