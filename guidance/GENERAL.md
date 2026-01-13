## General Coding Guidelines

These principles apply to all languages and frameworks. Language- or framework‑specific rules belong in their respective files.

### Core Principles
- Prefer clear, succinct code over clever code.
- Make code easy to unit test; isolate side effects.
- Strongly prefer composition over inheritance.
- Fail fast with meaningful exceptions and messages.
- Keep functions small and single-purpose (one reason to change).
- Name things descriptively; avoid abbreviations except well-known ones (`id`, `url`, `html`, loop index `i`).
- Do not use abbreviations in variable names. Exceptions only for very common abbreviations - e.g., `$id`, `$url`, `$html`, `$i` (for loops), `$sut` (for 'system under test' in tests).
    - For example, do not use `$adminCreds`; instead, prefer `$adminCredentials`.

### Architecture
- Use Ports & Adapters (Hexagonal) principles to decouple core logic from infrastructure.
  - Introduce interfaces in front of external APIs, databases, system clock/time, filesystem, network, etc. so they can be faked in tests.
  - Where wrapping third‑party libraries is sensible, hide them behind interfaces to ease future replacement.
- Public interfaces should return minimal DTOs (or collections) containing only the data needed for the task at hand.
- Keep domain logic framework‑agnostic. Framework code should be at the edges.

### Testing
- Aim for fast, deterministic unit tests; favour pure functions and injected dependencies.
- Use fakes/stubs over mocking where possible; mock only your own interfaces.
- Test behaviours (inputs/outputs, state changes) rather than implementation details.

### Code Health
- Keep cyclomatic complexity low; split complex branches into smaller functions.
- Avoid duplication (DRY) but don't over-abstract prematurely (rule of three).
- Document non-obvious decisions with short comments or ADRs; prefer self-explanatory code.
- Use static analysis and linters; fix or justify warnings.
- Avoid non-standard characters (e.g., non-breaking hyphens) in the documentation.

### Security & Reliability
- Validate and sanitise inputs at boundaries.
- Treat all I/O as fallible; handle and log errors with actionable context.
- Do not leak sensitive data in logs or exceptions.
