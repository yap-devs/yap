# AGENTS.md - YAP (Yet Another Panel)

Laravel 12 + React 19 (Inertia.js 3) application for managing VPN/proxy subscriptions.
PHP 8.3+ backend, JavaScript/JSX frontend, Filament admin panel, Tailwind CSS.

## Development Environment

If the working directory is on a Windows filesystem mount (e.g. path starts with `/mnt/c/`,
`/mnt/d/`, etc.), **always use the Windows-native tools** (`php.exe`, `composer.bat`,
`node.exe`, `npm.cmd`, etc.) via `powershell.exe -Command "..."` or `cmd.exe /c "..."`
instead of the WSL-local binaries. The WSL environment may be missing PHP extensions
(e.g. `ext-yaml`, `ext-intl`) that the Windows PHP installation has. **Never** use
`--ignore-platform-reqs` or `--ignore-platform-req` to work around missing extensions —
ask the user to run the command on Windows or install the missing extensions first.

## Build / Lint / Test Commands

```bash
# Backend
composer install                          # Install PHP dependencies
php artisan test                          # Run all tests (Pest PHP)
php artisan test --filter=ProfileTest     # Run a single test file
php artisan test --filter="profile page"  # Run a single test by name
php artisan test tests/Feature/Auth       # Run tests in a directory
php artisan test --parallel               # Run tests in parallel
./vendor/bin/pest tests/Browser --browser chrome  # Run Pest 4 browser tests (requires Playwright Chromium)
./vendor/bin/pint                         # Format PHP (Laravel Pint)
./vendor/bin/pint --test                  # Check formatting without fixing

# Frontend
npm install                               # Install JS dependencies
npx playwright install chromium           # Install browser binary for Pest browser tests
npm run build                             # Production build (Vite)
npm run dev                               # Dev server with HMR

# Artisan
php artisan migrate                       # Run migrations
php artisan db:seed                       # Seed database
php artisan route:list                    # List all routes
```

## Code Standards (Owner Rules)

- Always use English for code comments, even when conversing in other languages
- No space-only lines; use empty newlines instead
- Match existing comment language in each file
- **Never** use foreign keys or cascades in any RDBMS
- **Never** reset or wipe any database; roll back modified parts only, ask user if not possible
- **Never** commit changes via git automatically; always review diffs first
- If frontend is changed, run `npm run build` before finishing

## Project Structure

```
app/
  Console/Commands/     # Artisan commands
  Filament/Resources/   # Admin panel (Filament 3)
  Http/Controllers/     # Web controllers (Inertia responses)
  Http/Middleware/       # Custom middleware
  Jobs/                 # Queued/sync jobs
  Models/               # Eloquent models (all use SoftDeletes)
  Notifications/        # Mail/notification classes
  Observers/            # Model observers (attribute-based registration)
  Services/             # Business logic services
resources/js/
  Components/           # Reusable React components (PascalCase files)
  Layouts/              # AuthenticatedLayout, GuestLayout
  Pages/                # Page components organized by feature
  Utils/                # Utility functions (camelCase files)
tests/
  Feature/              # Integration tests (use RefreshDatabase)
  Browser/              # Pest 4 browser tests (use Playwright)
  Unit/                 # Unit tests
```

## PHP Code Style

### Formatting
- 4-space indentation
- Single quotes for strings; double quotes only for interpolation
- Short array syntax `[]` exclusively
- One blank line between methods
- No blank line after the class opening brace

### Imports
- Alphabetical within a single `use` block (no blank-line group separation)
- App namespace first, then framework/vendor, then PHP builtins:
```php
use App\Models\User;
use App\Services\V2rayService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;
```

### Naming
- Variables: `snake_case` (`$vmess_servers`, `$out_trade_no`)
- Methods: `camelCase`
- Classes: `PascalCase`
- Constants: `UPPER_SNAKE_CASE` on classes (`const STATUS_PAID = 'paid'`)
- Status values: string constants, not enums

### Type Hints & Return Types
- Always add parameter type hints
- Return types on lifecycle methods (`boot(): void`, `up(): void`, `handle(): void`)
- Controller action methods and relationship methods typically omit return types
- Use `/** @var Type $var */` inline doc for local variable type hints

### Models
- All models use `SoftDeletes` trait
- Use `Attribute::make()` for accessors (modern API)
- `Model::unguard()` is set globally; `$fillable` arrays still present for documentation
- Observer registration via PHP 8 attributes: `#[ObservedBy(UserObserver::class)]`

### Error Handling
- `abort_if()` for HTTP guard clauses: `abort_if(!$condition, 404)`
- `throw_if()` for service-level errors
- try-catch with non-capturing catches for external services: `catch (InvalidStateException)`
- `logger()->error()` or `logger()->driver('job')->log()` for background task errors
- User-facing errors via `->withErrors(['error' => '...'])`

### Controllers
- Return `Inertia::render('Page', compact(...))` for page responses
- Use `$request->user()` with `/** @var User $user */` type hint
- Guard clauses at top of methods with `abort_if()`

### Migrations
- Anonymous class style: `return new class extends Migration { ... }`
- `$table->id()` for primary keys
- `$table->unsignedBigInteger('..._id')` for references (NO foreign key constraints)
- `$table->timestamps()` and `$table->softDeletes()` on every table
- Use `->comment()` for column documentation
- `down()` uses `Schema::dropIfExists()`
- Table names: plural snake_case (`user_packages`, `vmess_servers`)

### Services
- Constructor promotion with `readonly`: `public function __construct(private readonly string $server)`
- `readonly class` when fully immutable
- Public methods for API, private for internals

## JavaScript / React Code Style

### Formatting
- 2-space indentation (per .editorconfig for `resources/js/**`)
- Semicolons in component files
- Double quotes for JSX attributes, single quotes for JS strings
- Functional components only (no class components)

### Imports
- Framework/library imports first, then local components, then utils
- Path alias `@/` maps to `resources/js/`
```jsx
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import {Head, Link, router} from '@inertiajs/react';
import {useState} from "react";
import {formatBytes} from "@/Utils/formatBytes";
```

### Components
- `export default function ComponentName({prop1, prop2})` for pages
- Props always destructured in function signature
- Use inner render functions (`renderSection()`) for complex JSX blocks
- Tailwind utility classes inline (no extraction libraries)

### State & Navigation
- `useState` for local state (no external state management)
- `router.visit()`, `router.get()`, `router.post()` from Inertia for navigation
- `route()` helper from Ziggy for named routes

### Naming
- Components/files: `PascalCase` (`AuthenticatedLayout.jsx`)
- Variables/functions: `camelCase`
- Page components: `Index.jsx` inside feature folders (`Package/Index.jsx`)
- Utility files: `camelCase` (`formatBytes.js`)

## Testing (Pest PHP)

- Test descriptions are lowercase human-readable sentences
- Use `test()` function (preferred) or `it()` for assertions
- Feature tests auto-apply `RefreshDatabase` via `tests/Pest.php`
- Browser tests use Pest 4 Browser and Playwright; run them separately with `./vendor/bin/pest tests/Browser --browser chrome`
- Install Playwright Chromium with `npx playwright install chromium` before running browser tests
- Create users with `User::factory()->create()`
- Authenticate with `$this->actingAs($user)`
- Fluent response assertions: `$response->assertSessionHasNoErrors()->assertRedirect(...)`
- Mix of `$this->assertSame()` and Pest's `expect()->toBe()` (prefer `expect()` for new tests)

```php
test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get('/profile');

    $response->assertOk();
});
```

## Key Architecture Notes

- Inertia.js bridges Laravel and React (no separate API routes)
- Data passed to React via `Inertia::render('Page', compact(...))` in controllers
- Payment gateways: Alipay, BEPUSDT (crypto)
- GitHub OAuth login + GitHub Sponsors webhook integration
- Scheduled commands in `routes/console.php` using `Schedule::command()`
- `BepusdtService` registered as singleton in `AppServiceProvider`
- HTTPS forced globally via `URL::forceScheme('https')`

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.3. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== tests rules ===

# Test Enforcement

- Add or update tests for behavior and logic changes when a test provides meaningful regression coverage.
- Pure copy, styling, and layout-only changes do not require new or updated tests.
- When test coverage applies, run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/Pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-react-development` when working with Inertia client-side patterns.

# Inertia v3

- Use all Inertia features from v1, v2, and v3. Check the documentation before making changes to ensure the correct approach.
- New v3 features: standalone HTTP requests (`useHttp` hook), optimistic updates with automatic rollback, layout props (`useLayoutProps` hook), instant visits, simplified SSR via `@inertiajs/vite` plugin, custom exception handling for error pages.
- Carried over from v2: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.
- Axios has been removed. Use the built-in XHR client with interceptors, or install Axios separately if needed.
- `Inertia::lazy()` / `LazyProp` has been removed. Use `Inertia::optional()` instead.
- Prop types (`Inertia::optional()`, `Inertia::defer()`, `Inertia::merge()`) work inside nested arrays with dot-notation paths.
- SSR works automatically in Vite dev mode with `@inertiajs/vite` - no separate Node.js server needed during development.
- Event renames: `invalid` is now `httpException`, `exception` is now `networkError`.
- `router.cancel()` replaced by `router.cancelAll()`.
- The `future` configuration namespace has been removed - all v2 future options are now always enabled.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== laravel/v12 rules ===

# Laravel 12

- CRITICAL: ALWAYS use `search-docs` tool for version-specific Laravel documentation and updated code examples.
- This project uses the streamlined Laravel 11+ structure: register middleware, exceptions, and routing in `bootstrap/app.php` and service providers in `bootstrap/providers.php`. There is no `app/Http/Kernel.php` or `app/Console/Kernel.php`, and commands in `app/Console/Commands/` auto-register.

## Database

- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.

- Laravel 12 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models

- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

# Pest

- This project uses Pest. Create tests with `php artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/pest` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.
- After the feature tests pass, ask the user to run the complete suite with `php artisan test --compact`.

=== inertia-react/core rules ===

# Inertia + React

- IMPORTANT: Activate `inertia-react-development` when working with Inertia React client-side patterns.

</laravel-boost-guidelines>

## Agent skills

### Issue tracker

Issues are tracked in this repository's GitHub Issues. See `docs/agents/issue-tracker.md`.

### Domain docs

This is a single-context repository. See `docs/agents/domain.md`.

### Node provisioning and core upgrades

Use `.agents/skills/yap-node-operations/SKILL.md` for Agent machines, destinations and relay entries. Its runbooks are `docs/agents/node-operations.md` and `docs/agents/v2fly-upgrade.md`. Hosting instructions are in `docs/deployment/README.md`.
