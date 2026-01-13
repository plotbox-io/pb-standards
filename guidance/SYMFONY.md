## Symfony Coding Guidelines

These rules focus on Symfony specifics. General PHP guidance lives in `PHP.md` and universal principles in `GENERAL.md`.

### Controllers & HTTP
- Keep controllers thin: validate input, delegate to services, return responses.
- Use argument value resolvers and ParamConverters judiciously; keep magic minimal and explicit.
- Prefer attribute routing; group routes by feature/module.
- Return DTOs serialised via normalisers or view models; avoid leaking Doctrine entities directly to the outside world.

### Services & Dependency Injection
- Define services as `autowire: true`, `autoconfigure: true` where practical; use explicit service wiring for special cases.
- Keep service constructors simple; inject interfaces, not concrete classes, where an abstraction exists.
- Avoid using the container directly (`ContainerInterface`) in services; favour constructor injection.
- Use `Kernel` events and subscribers/listeners for cross-cutting concerns (logging, auditing), not inside controllers.

### Configuration & Environment
- Keep environment‑specific config in `config/packages/{env}`; keep defaults in `config/packages`.
- Read environment only in configuration; inject typed config values into services.
- Prefer attributes over annotations for configuration in modern Symfony versions.

### Validation & Forms
- Use the Validator component for input validation; declare constraints via attributes or YAML.
- Keep complex validation logic in custom constraints/validators.
- Prefer DTOs (command/query objects) over binding entities directly to forms/APIs.

### Doctrine ORM
- Keep entities simple and persistence‑focused; move domain logic to services/value objects.
- Use typed properties and enums where supported; configure attribute mapping.
- Repositories encapsulate query logic; avoid writing DQL in controllers/services.
- Avoid N+1 queries; use `fetch joins` and `EntityManager::clear()` for long‑running processes.
- Wrap multi-step writes in transactions via `EntityManager` or `doctrine.transactions`.

### Messenger, Queues & Async
- Use Messenger for async commands/events; keep handlers idempotent and small.
- Configure transports with retry and backoff; use `failure_transport` for dead letters.
- Prefer `dispatchSync` for in‑process orchestration when appropriate.

### HTTP Clients & External APIs
- Use `symfony/http-client` with declarative options; wrap in dedicated client classes behind interfaces.
- Centralise authentication, timeouts, and retries; log requests minimally and safely.

### Caching & Performance
- Use Cache component with pool names and taggable caches where supported.
- Apply HTTP caching (ETag/Last‑Modified, Cache‑Control) on read endpoints.
- Use `LazyCommand`, `LazyEventDispatcher`, and other lazy services to optimise boot time where needed.

### Console & CLI
- Keep commands thin; delegate to services.
- Use input validation and meaningful exit codes; keep commands idempotent when rerun.

### Security
- Configure security in `security.yaml` (or attributes) with clear firewalls and access controls.
- Use voters for complex authorisation logic; avoid sprinkling `isGranted()` checks throughout services.
- Never expose stack traces or sensitive configuration in production; use proper error pages/handlers.

### Testing
- Use `KernelTestCase`/`WebTestCase` for integration/HTTP tests; use unit tests for services.
- Use test containers and `DAMADoctrineTestBundle`/transactions to keep DB tests isolated and fast where applicable.
- Create test fixtures via Foundry/Faker or factories; avoid brittle shared fixtures.
