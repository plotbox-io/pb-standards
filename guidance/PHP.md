## PHP Coding Standards and Best Practices

These rules cover pure PHP. Framework‑specific guidance is in the Laravel and Symfony documents.

### Language Level
- Use the latest PHP features available to the project runtime (e.g., PHP 8.2+/8.3+/8.4 where applicable).
- Always declare strict types in new files: `declare(strict_types=1);` Be careful when adding to legacy files.

### Types & Contracts
- Use native type hints for all parameters and return types.
- Use `self`/`static` return types for fluent APIs or builders as appropriate.
- Prefer value objects and enums over string/int primitives when they represent a domain concept.
- For iterables/arrays, document generics with PHPDoc (e.g., `array<int, User>`, `list<Order>`), and validate contents where critical.
- Prefer to strongly type variables via method input/output rather than `@var` annotations where possible.
- DO NOT use legacy/ambiguous iterable types like int[], string[], etc. as they do not convey the intended structure (list vs map) or type safety.
- DO NOT add annotations like /** @var list<string> */ above constant arrays. This is redundant and adds unnecessary noise.

### Object Design
- Prefer composition over inheritance. Make classes `final` by default unless designed for extension.
- Use constructor injection for dependencies. Avoid service locators and globals.
- Avoid static methods for behaviour; acceptable exceptions: named constructors, pure factories, constants, and stateless helpers.
- Keep properties and methods `private` by default; use `protected` only when there is a concrete inheritance need.
- Use traits sparingly; favour small dedicated classes.
- DTOs may be `readonly` (class‑level) where appropriate; avoid sprinkling `readonly` on many individual properties.

### Code Style
- FQCNs should be avoided in code. Use 'use' statements instead.
- Use native type hints and return types on all methods and functions.
- Use self or static return types on methods that return the current class instance (e.g., fluent setters, builders, etc.).
- Iterable types and arrays should be strongly typed with phpdoc generics where possible (e.g. array<int, User>, iterable<User>, list<User>).
- Always use strict types on new code files (declare(strict_types=1);). Be cautious when adding strict types to existing files.
- Code comments should only be used where the code is not self-explanatory. Prefer clear code over comments.
- Use strict comparisons (===, !==) unless there is a very good reason not to.
- Use single quotes for strings unless you need to use double quotes (e.g. for interpolation).
- Use short array syntax ([]).
- Use type-safe functions (e.g. in_array() with strict parameter set to true).
- Never use the `empty()` function to check for null values. Prefer truthy/falsey checks for terseness, `isset()`, or strict comparison operators.
- Use @inheritDoc in child classes or interface implementations for implemented or overridden methods (rather than repeating the PHPDoc description).
- Use dependency injection via constructor injection for service dependencies.
- Classes should be final by default unless they are explicitly designed for inheritance.
- Use private visibility for properties and methods by default. Only use protected visibility when necessary for inheritance.
- Use traits sparingly and only when there is a very good reason to do so.
- Avoid using the readonly property modifier in general except where it is very beneficial (e.g., for simple DTOs), and in those cases only on the class level rather than individual properties. The extra noise is not usually worth the minor benefits.
- Use constructor property promotion for DTOs.
- Use named arguments when instantiating DTOs with many parameters for clarity.
- When referring to repositories as dependencies, their variable name should be like `$somethingRepository` (e.g., 'CustomerRepository' would be `$customerRepository`).
    - Some long-winded repository names can be shortened for brevity (e.g., 'TemporaryCustomerCredentialRepository' could be `$credentialRepository`).
- Do not add a trailing comma after the last item in an array or argument list or function call argument list.

### Errors & Exceptions
- Throw domain‑specific exceptions with clear messages; include actionable context but no sensitive data.
- Don’t use return codes for error handling. Avoid suppressing exceptions.

### Functions & Methods
- Keep functions small; single responsibility.
- Method signatures should be split across multiple lines when they exceed 100 characters in length.
- Use strict comparisons (`===`, `!==`).
- Use short array syntax `[]`; do not add a trailing comma after the last item in an array or argument list or function call argument list.
- Use single quotes for strings unless interpolation is required.
- Prefer type‑safe library calls (e.g., `in_array($needle, $haystack, true)`).

### Naming & Imports
- Avoid abbreviations in identifiers except well‑known ones (`$id`, `$url`, `$html`, loop index `$i`, `$sut` in tests). For example, prefer `$adminCredentials` over `$adminCreds`.
- Avoid FQCNs inline; import with `use` statements.
- Repositories injected as dependencies should be named `$thingRepository` (e.g., `$customerRepository`); very long names can be shortened thoughtfully (e.g., `$credentialRepository`).

### Documentation
- Prefer self‑documenting code. Use comments for why, not what.
- In classes implementing interfaces or extending abstract classes, use `@inheritDoc` on overridden methods instead of duplicating descriptions.

### Testing & Tooling
- Design for testability: isolate I/O behind interfaces; pass time/clock, filesystem, and external clients as dependencies.
- Focus unit testing primarily on (use-case) handlers.
- Prefer fakes over mocks in unit tests (i.e., make fake implementations of interfaces rather than mocking them).
- Use PHPUnit for unit tests.
- Each test name should start with `should_`.
- Each test should be comprised of only methods that start with `given_`, `when_`, or `then_`.
- All main methods (test, givens, whens, thens) should use plain business language and avoid technical terms (e.g., `should_create_user` rather than `should_invoke_create_method_on_user_repository`).
- All main methods (test, givens, whens, thens) should return `void` and not have any parameters. Prefer using class properties to share state between them.
- Within the `given`, `when`, and `then` methods, other utility/technical methods can be used to keep the code DRY. These can be in `camelCase` and can use more technical terms.
- Sometimes `given` statements may be implicit (i.e., the default set up state already has the necessary preconditions). In this case, it is acceptable to omit the `given` statement (or use a single-line comment to indicate the implicit `given`).
