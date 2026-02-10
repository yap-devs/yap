# AGENTS.md - YAP (Yet Another Panel)

Laravel 11 + React 18 (Inertia.js) application for managing VPN/proxy subscriptions.
PHP 8.2+ backend, JavaScript/JSX frontend, Filament admin panel, Tailwind CSS.

## Build / Lint / Test Commands

```bash
# Backend
composer install                          # Install PHP dependencies
php artisan test                          # Run all tests (Pest PHP)
php artisan test --filter=ProfileTest     # Run a single test file
php artisan test --filter="profile page"  # Run a single test by name
php artisan test tests/Feature/Auth       # Run tests in a directory
php artisan test --parallel               # Run tests in parallel
./vendor/bin/pint                         # Format PHP (Laravel Pint)
./vendor/bin/pint --test                  # Check formatting without fixing

# Frontend
npm install                               # Install JS dependencies
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
- Payment gateways: Alipay, BEPUSDT (crypto), Futoon
- GitHub OAuth login + GitHub Sponsors webhook integration
- Scheduled commands in `routes/console.php` using `Schedule::command()`
- `BepusdtService` registered as singleton in `AppServiceProvider`
- HTTPS forced globally via `URL::forceScheme('https')`
