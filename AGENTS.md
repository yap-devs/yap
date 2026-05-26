# AGENTS.md - YAP (Yet Another Panel)

Laravel 12 + React 18 (Inertia.js) application for managing VPN/proxy subscriptions.
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

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.3
- filament/filament (FILAMENT) - v5
- inertiajs/inertia-laravel (INERTIA_LARAVEL) - v1
- laravel/framework (LARAVEL) - v12
- laravel/prompts (PROMPTS) - v0
- laravel/sanctum (SANCTUM) - v4
- laravel/socialite (SOCIALITE) - v5
- laravel/telescope (TELESCOPE) - v5
- livewire/livewire (LIVEWIRE) - v4
- tightenco/ziggy (ZIGGY) - v2
- laravel/boost (BOOST) - v2
- laravel/breeze (BREEZE) - v2
- laravel/mcp (MCP) - v0
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12
- @inertiajs/react (INERTIA_REACT) - v1
- react (REACT) - v18
- tailwindcss (TAILWINDCSS) - v4

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

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

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

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/Pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-react-development` when working with Inertia client-side patterns.

# Inertia v1

- Inertia v1 does not support the following v2 features: deferred props, infinite scrolling (merging props + `WhenVisible`), lazy loading on scroll, polling, or prefetching. Do not use these.

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

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== laravel/v12 rules ===

# Laravel 12

- CRITICAL: ALWAYS use `search-docs` tool for version-specific Laravel documentation and updated code examples.
- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

## Laravel 12 Structure

- In Laravel 12, middleware are no longer registered in `app/Http/Kernel.php`.
- Middleware are configured declaratively in `bootstrap/app.php` using `Application::configure()->withMiddleware()`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- The `app/Console/Kernel.php` file no longer exists; use `bootstrap/app.php` or `routes/console.php` for console configuration.
- Console commands in `app/Console/Commands/` are automatically available and do not require manual registration.

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

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- The `{name}` argument should not include the test suite directory. Use `php artisan make:test --pest SomeFeatureTest` instead of `php artisan make:test --pest Feature/SomeFeatureTest`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.

=== inertia-react/core rules ===

# Inertia + React

- IMPORTANT: Activate `inertia-react-development` when working with Inertia React client-side patterns.

=== filament/filament rules ===

## Filament

- Filament is a Laravel UI framework built on Livewire, Alpine.js, and Tailwind CSS. UIs are defined in PHP via fluent, chainable components. Follow existing conventions in this app.
- Use the `search-docs` tool for official documentation on Artisan commands, code examples, testing, relationships, and idiomatic practices. If `search-docs` is unavailable, refer to https://filamentphp.com/docs.

### Artisan

- Always use Filament-specific Artisan commands to create files. Find available commands with the `list-artisan-commands` tool, or run `php artisan --help`.
- Inspect required options before running, and always pass `--no-interaction`.

### Patterns

Always use static `make()` methods to initialize components. Most configuration methods accept a `Closure` for dynamic values.

Use `Get $get` to read other form field values for conditional logic:

<code-snippet name="Conditional form field visibility" lang="php">
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;

Select::make('type')
    ->options(CompanyType::class)
    ->required()
    ->live(),

TextInput::make('company_name')
    ->required()
    ->visible(fn (Get $get): bool => $get('type') === 'business'),

</code-snippet>

Use `Set $set` inside `->afterStateUpdated()` on a `->live()` field to mutate another field reactively. Prefer `->live(onBlur: true)` on text inputs to avoid per-keystroke updates:

<code-snippet name="Reactive field update" lang="php">
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Str;

TextInput::make('title')
    ->required()
    ->live(onBlur: true)
    ->afterStateUpdated(fn (Set $set, ?string $state) => $set(
        'slug',
        Str::slug($state ?? ''),
    )),

TextInput::make('slug')
    ->required(),

</code-snippet>

Compose layout by nesting `Section` and `Grid`. Children need explicit `->columnSpan()` or `->columnSpanFull()`:

<code-snippet name="Section and Grid layout" lang="php">
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;

Section::make('Details')
    ->schema([
        Grid::make(2)->schema([
            TextInput::make('first_name')
                ->columnSpan(1),
            TextInput::make('last_name')
                ->columnSpan(1),
            TextInput::make('bio')
                ->columnSpanFull(),
        ]),
    ]),

</code-snippet>

Use `Repeater` for inline `HasMany` management. `->relationship()` with no args binds to the relationship matching the field name:

<code-snippet name="Repeater for HasMany" lang="php">
use Filament\Forms\Components\Repeater;

Repeater::make('qualifications')
    ->relationship()
    ->schema([
        TextInput::make('institution')
            ->required(),
        TextInput::make('qualification')
            ->required(),
    ])
    ->columns(2),

</code-snippet>

Use `state()` with a `Closure` to compute derived column values:

<code-snippet name="Computed table column value" lang="php">
use Filament\Tables\Columns\TextColumn;

TextColumn::make('full_name')
    ->state(fn (User $record): string => "{$record->first_name} {$record->last_name}"),

</code-snippet>

Use `SelectFilter` for enum or relationship filters, and `Filter` with a `->query()` closure for custom logic:

<code-snippet name="Table filters" lang="php">
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;

SelectFilter::make('status')
    ->options(UserStatus::class),

SelectFilter::make('author')
    ->relationship('author', 'name'),

Filter::make('verified')
    ->query(fn (Builder $query) => $query->whereNotNull('email_verified_at')),

</code-snippet>

Actions are buttons that encapsulate optional modal forms and behavior:

<code-snippet name="Action with modal form" lang="php">
use Filament\Actions\Action;

Action::make('updateEmail')
    ->schema([
        TextInput::make('email')
            ->email()
            ->required(),
    ])
    ->action(fn (array $data, User $record) => $record->update($data)),

</code-snippet>

### Testing

Testing setup (requires `pestphp/pest-plugin-livewire` in `composer.json`):

- Always call `$this->actingAs(User::factory()->create())` before testing panel functionality.
- For edit pages, pass `['record' => $user->id]`, use `->call('save')` (not `->call('create')`), and do not assert `->assertRedirect()` (edit pages do not redirect after save).

<code-snippet name="Table test" lang="php">
use function Pest\Livewire\livewire;

livewire(ListUsers::class)
    ->assertCanSeeTableRecords($users)
    ->searchTable($users->first()->name)
    ->assertCanSeeTableRecords($users->take(1))
    ->assertCanNotSeeTableRecords($users->skip(1));

</code-snippet>

<code-snippet name="Create resource test" lang="php">
use function Pest\Laravel\assertDatabaseHas;

livewire(CreateUser::class)
    ->fillForm([
        'name' => 'Test',
        'email' => 'test@example.com',
    ])
    ->call('create')
    ->assertNotified()
    ->assertHasNoFormErrors()
    ->assertRedirect();

assertDatabaseHas(User::class, [
    'name' => 'Test',
    'email' => 'test@example.com',
]);

</code-snippet>

<code-snippet name="Edit resource test" lang="php">
livewire(EditUser::class, ['record' => $user->id])
    ->fillForm(['name' => 'Updated'])
    ->call('save')
    ->assertNotified()
    ->assertHasNoFormErrors();

assertDatabaseHas(User::class, [
    'id' => $user->id,
    'name' => 'Updated',
]);

</code-snippet>

<code-snippet name="Testing validation" lang="php">
livewire(CreateUser::class)
    ->fillForm([
        'name' => null,
        'email' => 'invalid-email',
    ])
    ->call('create')
    ->assertHasFormErrors([
        'name' => 'required',
        'email' => 'email',
    ])
    ->assertNotNotified();

</code-snippet>

Use `->callAction(DeleteAction::class)` for page actions, or `->callAction(TestAction::make('name')->table($record))` for table actions:

<code-snippet name="Calling actions" lang="php">
use Filament\Actions\Testing\TestAction;

livewire(ListUsers::class)
    ->callAction(TestAction::make('promote')->table($user), [
        'role' => 'admin',
    ])
    ->assertNotified();

</code-snippet>

### Correct Namespaces

- Form fields (`TextInput`, `Select`, `Repeater`, etc.): `Filament\Forms\Components\`
- Infolist entries (`TextEntry`, `IconEntry`, etc.): `Filament\Infolists\Components\`
- Layout components (`Grid`, `Section`, `Fieldset`, `Tabs`, `Wizard`, etc.): `Filament\Schemas\Components\`
- Schema utilities (`Get`, `Set`, etc.): `Filament\Schemas\Components\Utilities\`
- Table columns (`TextColumn`, `IconColumn`, etc.): `Filament\Tables\Columns\`
- Table filters (`SelectFilter`, `Filter`, etc.): `Filament\Tables\Filters\`
- Actions (`DeleteAction`, `CreateAction`, etc.): `Filament\Actions\`. Never use `Filament\Tables\Actions\`, `Filament\Forms\Actions\`, or any other sub-namespace for actions.
- Icons: `Filament\Support\Icons\Heroicon` enum (e.g., `Heroicon::PencilSquare`)

### Common Mistakes

- **Never assume public file visibility.** File visibility is `private` by default. Always use `->visibility('public')` when public access is needed.
- **Never assume full-width layout.** `Grid`, `Section`, `Fieldset`, and `Repeater` do not span all columns by default.
- **Use `Select::make('author_id')->relationship('author', 'name')` for BelongsTo fields.** `BelongsToSelect` does not exist in v4.
- **`Repeater` uses `->schema()`, not `->fields()`.**
- **Never add `->dehydrated(false)` to fields that need to be saved.** It strips the value from form state before `->action()` or the save handler runs. Only use it for helper/UI-only fields.
- **Use correct property types when overriding `Page`, `Resource`, and `Widget` properties.** These properties have union types or changed modifiers that must be preserved:
  - `$navigationIcon`: `protected static string | BackedEnum | null` (not `?string`)
  - `$navigationGroup`: `protected static string | UnitEnum | null` (not `?string`)
  - `$view`: `protected string` (not `protected static string`) on `Page` and `Widget` classes

</laravel-boost-guidelines>
