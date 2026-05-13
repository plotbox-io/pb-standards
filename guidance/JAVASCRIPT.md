## JavaScript Standards and Best Practices

Vue-specific patterns live in `VUE.md`; Vitest testing conventions live in `VITEST.md`;
general principles in `GENERAL.md`.

### Tooling
- Use ESLint with strict config; fix all warnings before committing.
- Run lint and tests on changed modules in CI.
- Use Vitest for all JS unit tests. See `VITEST.md` for testing conventions, file layout,
  naming, and what is and is not in scope for unit tests.
