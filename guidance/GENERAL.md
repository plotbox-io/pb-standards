## General Coding Guidelines

Applies to all languages. Language-specific rules live in their respective files.

### Core Principles
- Prefer clear, succinct code over clever code.
- Make code easy to unit test; isolate side effects.
- Strongly prefer composition over inheritance.
- Fail fast with meaningful exceptions and messages.
- Keep functions small and single-purpose.
- Name things descriptively. Avoid abbreviations except well-known ones: `id`, `url`, `html`, loop index `i`, `sut` in tests.

### Architecture
- Use Ports & Adapters (Hexagonal) to decouple core logic from infrastructure: interfaces in front of external APIs, databases, clock, filesystem, network.
- Public interfaces return minimal DTOs with only the data needed.
- Keep domain logic framework-agnostic; framework code lives at the edges.

### Testing
- Fast, deterministic unit tests; favour pure functions and injected dependencies.
- Fakes/stubs over mocks; mock only your own interfaces.
- Test behaviours (inputs/outputs, state changes), not implementation details.

### Code Health
- Low cyclomatic complexity; split complex branches into smaller functions.
- DRY but don't over-abstract prematurely (rule of three).
- Document non-obvious decisions with comments or ADRs; prefer self-explanatory code.
- Use static analysis and linters; fix or justify warnings.

### Code Smells

Recognise and avoid these named anti-patterns. Naming them gives teams a shared vocabulary for code review.

- **Primitive Obsession** — using raw strings, ints, or arrays to represent domain concepts. Prefer value objects, enums, or typed DTOs (e.g. a `Status` type rather than a plain `string $status`).
- **Long Parameter List** — more than 3–4 parameters signals a missing object or DTO. Group related parameters into a dedicated input type.
- **Large Class / Module** — a class or module that keeps growing and takes on unrelated responsibilities. Split by single responsibility.
- **Feature Envy** — a method that accesses another class's data more than its own. It probably belongs in that other class.
- **Message Chains** — `a->getB()->getC()->getValue()`. Ask the first object for what you need; don't reach through a chain of intermediaries.
- **Dead Code** — unused code should be deleted, not commented out. Version control exists for recovery.
- **Speculative Generality** — abstractions added for imagined future needs that don't exist yet (YAGNI). Build for what is needed now; refactor when the need is real.

### Security & Reliability
- Validate and sanitise inputs at boundaries.
- Treat all I/O as fallible; log errors with actionable context.
- Never leak sensitive data in logs or exceptions.
