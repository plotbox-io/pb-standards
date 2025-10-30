## Laravel Coding Guidelines

These rules focus on Laravel specifics. General PHP guidance lives in `PHP.md` and universal principles in `GENERAL.md`.

### General
- Avoid using facades in new code. Use dependency injection instead
- Avoid using the global helper functions in new code. Use dependency injection instead

### Eloquent Models
- Follow Laravel naming and table conventions unless there is a strong reason not to.
- Prefer guarded domain logic outside of models (services/domain classes) to keep models focused on persistence concerns.
- Use typed properties and casts to ensure consistent types.
- Avoid business logic in accessors/mutators beyond shaping data.
- Use factories for test data and seeding; keep them lightweight and explicit.

### Object Creation
- Provide a named constructor for domain‑meaningful creation, e.g., `public static function makeNew(...) : self`.
  - Use `makeNew()` instead of using `new` in calling code to make intent explicit.
  - `makeNew()` accepts only required properties; optional properties may have sensible defaults.
  - Use strict, non‑nullable types unless a value is truly optional (e.g., nullable DB column).
  - Keep the standard constructor public to allow ORM hydration.

### Controllers & Routing
- Keep controllers thin: validate request, call services, return resources/responses.
- Use Form Requests for validation; never trust raw input.
- Resource controllers and route model binding should be used where appropriate.

### Services & Dependency Injection
- Encapsulate business logic in service classes; inject repositories/clients via constructors.
- Avoid using facades in domain/services. Facades are acceptable at framework edges (controllers, console commands) for convenience.
- Do not use global helper functions inside domain logic; inject dependencies instead.

### Database & Repositories
- Prefer query scopes and dedicated query classes for complex queries.
- Use repositories where they provide a useful abstraction (e.g., aggregating multiple models or external data sources).
- Use transactions for multi‑step writes; prefer `DB::transaction()` with retry where needed.

### API Resources & Serialization
- Use Laravel API Resources for HTTP JSON responses to decouple storage from representation.
- Keep resources stable; avoid leaking internal field names.

### Events, Jobs & Queues
- Use events to decouple side effects (e.g., notifications) from write paths.
- Queue long‑running work; keep jobs idempotent and small. Use unique jobs when appropriate.
- Handle failures with retries and backoff; capture contextual data but never sensitive secrets.

### Caching & Performance
- Use cache for expensive or slow queries; tag caches for targeted invalidation.
- Prefer `Cache::remember()` patterns; define sensible TTLs.
- Avoid N+1 queries; use `with()` eager loading intentionally.

### Configuration & Environment
- Configuration must be driven by `config/*.php`; never read env directly outside config.
- Use typed config accessors where possible; validate required configuration at boot time.

### Testing
- Use Pest or PHPUnit with Laravel’s testing utilities; prefer feature tests at boundaries and unit tests for services.
- Use database Refresh/Transaction traits wisely; seed minimally necessary data.
- Fake time/queue/mail/notifications where appropriate using Laravel fakes.

### Security
- Mass assignment: use `$fillable` appropriately or guarded patterns; validate and authorise all inputs.
- Authorisation: use policies and gates; never inline complex permissions in controllers.
- Escape output by default; trust Blade’s escaping and avoid `{!! !!}`.