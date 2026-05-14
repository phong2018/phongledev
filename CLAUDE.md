# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Laravel 13 application (PHP 8.3+) with Docker-based infrastructure. MySQL 8.0 as primary database, queue/cache/sessions stored in the database driver, Vite for frontend assets.

## Environment

The app runs inside Docker. All `php artisan` and `composer` commands should be executed inside the `app` container unless running tests locally with SQLite.

```bash
docker compose up -d
docker compose exec app php artisan <command>
docker compose exec app composer <command>
```

Services: **app** (PHP 8.4-FPM), **web** (Nginx → `http://localhost:8080`), **db** (MySQL 8.0 → `localhost:3306`), **phpmyadmin** (`http://localhost:8081`).

## Common Commands

```bash
composer run setup        # first-time setup (local)
composer run dev          # all dev processes via concurrently
composer run test         # clear config then run full test suite
php artisan test          # run tests directly
php artisan test tests/Feature/UserTest.php   # single file
php artisan test --filter=test_can_create_user # single method
./vendor/bin/pint         # fix code style
./vendor/bin/pint --test  # check only (CI)
npm run dev               # Vite watch
npm run build             # Vite production build
```

## Testing

Tests use SQLite in-memory (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`) — no Docker required. Queue is `sync`, cache/session use `array` driver.

- `tests/Unit/` — pure unit tests; mock repositories here, no HTTP or real DB
- `tests/Feature/` — full HTTP tests using `RefreshDatabase`

In unit tests, **mock the repository interface**, not the Eloquent model, to keep tests decoupled from the DB.

## Application Bootstrap

Laravel 13 uses the fluent `Application::configure()` API in [bootstrap/app.php](bootstrap/app.php) — there is no `Kernel.php`. Routing, middleware, and exception handling are all registered there.

- `routes/web.php` — web routes
- `routes/api.php` — API routes (register in `bootstrap/app.php` under `api:` key)
- `routes/console.php` — scheduled commands

Additional service providers are listed in [bootstrap/providers.php](bootstrap/providers.php).

To register `routes/api.php`, add `api:` to `withRouting()`:

```php
->withRouting(
    web: __DIR__.'/../routes/web.php',
    api: __DIR__.'/../routes/api.php',
    commands: __DIR__.'/../routes/console.php',
    health: '/up',
)
```

## Key Configuration

| Concern | Driver | Notes |
|---|---|---|
| Database | MySQL 8.0 | Host `db` inside Docker |
| Queue | `database` | Table `jobs`; use `queue:listen` in dev |
| Cache | `database` | Table `cache` |
| Session | `database` | Table `sessions` |
| Mail | `log` | No real mailer in dev |

## Code Style

Laravel Pint (PSR-12 / Laravel preset). The PostToolUse hook runs Pint automatically on every PHP file Claude edits. Run `./vendor/bin/pint` manually before committing.

Models use **PHP 8 attributes** for `fillable`/`hidden` — do not use `$fillable`/`$hidden` arrays:

```php
#[Fillable(['title', 'body', 'user_id'])]
#[Hidden(['secret'])]
class Post extends Model { ... }
```

---

## Architecture: Layered Request Flow

Every feature **must** follow this exact pipeline. Never skip a layer or put logic in the wrong layer.

```
HTTP Request
  └─► Route            routes/api.php
        └─► Controller  app/Http/Controllers/Api/V1/
              └─► FormRequest  app/Http/Requests/{Resource}/
                    └─► Service  app/Services/
                          └─► Repository  app/Repositories/
                                └─► Model  app/Models/
◄── JsonResource       app/Http/Resources/
◄── Exception          app/Exceptions/  (auto-rendered, no try/catch in controllers)
```

---

### 1. Routes — `routes/api.php`

Version all routes under a `v1` prefix. Use `apiResource` for standard CRUD.

```php
Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::apiResource('users', UserController::class);
    Route::apiResource('posts', PostController::class);
});
```

---

### 2. Controller — `app/Http/Controllers/Api/V1/`

**Rules:**
- One controller per resource, namespace `App\Http\Controllers\Api\V1`.
- Inject the **Service interface** via constructor — never the concrete class.
- Use **Route Model Binding** (`User $user`) — never accept raw `int $id` and look it up manually.
- Call exactly one service method per action. Return a `JsonResource`.
- No try/catch — exceptions render themselves.

```php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Contracts\UserServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends Controller
{
    public function __construct(
        private readonly UserServiceInterface $userService,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        return UserResource::collection($this->userService->paginate());
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = $this->userService->create($request->validated());

        return (new UserResource($user))->response()->setStatusCode(201);
    }

    public function show(User $user): UserResource
    {
        return new UserResource($user);
    }

    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        return new UserResource($this->userService->update($user, $request->validated()));
    }

    public function destroy(User $user): JsonResponse
    {
        $this->userService->delete($user);

        return response()->noContent(); // 204, no body
    }
}
```

---

### 3. Form Requests — `app/Http/Requests/{Resource}/`

**Rules:**
- Namespace per resource: `App\Http\Requests\User\StoreUserRequest`.
- `authorize()` returns `true` or calls a Policy — never put auth logic in the controller.
- `rules()` only. Use `'email:rfc'` for email fields. Use `Rule::unique()->ignore()` for updates.
- When using Route Model Binding, the resolved model is available as `$this->user` (matches route parameter name).

```php
// app/Http/Requests/User/StoreUserRequest.php
namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email:rfc', 'max:255', Rule::unique('users')],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }
}
```

```php
// app/Http/Requests/User/UpdateUserRequest.php
class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'  => ['sometimes', 'string', 'max:255'],
            // $this->user is the route-model-bound User instance
            'email' => ['sometimes', 'email:rfc', Rule::unique('users')->ignore($this->user->id)],
        ];
    }
}
```

---

### 4. Service — `app/Services/`

**Rules:**
- Interface in `app/Services/Contracts/FooServiceInterface.php`; concrete in `app/Services/FooService.php`.
- All business logic lives here: transformations, event dispatching, job dispatching, cross-model orchestration.
- Wrap multi-step write operations in `DB::transaction()`.
- Throw **domain exceptions** — never `abort()` or `HttpException`.
- Inject the Repository interface (never the Eloquent model directly for writes).
- Accept model instances from the controller (via route model binding) — don't re-fetch.

```php
// app/Services/Contracts/UserServiceInterface.php
namespace App\Services\Contracts;

use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

interface UserServiceInterface
{
    public function paginate(int $perPage = 15): LengthAwarePaginator;
    public function create(array $data): User;
    public function update(User $user, array $data): User;
    public function delete(User $user): void;
}
```

```php
// app/Services/UserService.php
namespace App\Services;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Contracts\UserServiceInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class UserService implements UserServiceInterface
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepo,
    ) {}

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->userRepo->paginate($perPage);
    }

    public function create(array $data): User
    {
        return DB::transaction(fn () => $this->userRepo->create($data));
    }

    public function update(User $user, array $data): User
    {
        return DB::transaction(fn () => $this->userRepo->update($user, $data));
    }

    public function delete(User $user): void
    {
        DB::transaction(fn () => $this->userRepo->delete($user));
    }
}
```

---

### 5. Repository — `app/Repositories/`

**Rules:**
- Interface in `app/Repositories/Contracts/FooRepositoryInterface.php`; concrete `EloquentFooRepository`.
- Only Eloquent queries here — no business logic, no events, no transactions.
- Always eager-load relationships when the caller will use them (pass `array $with = []` or use dedicated query methods) to prevent N+1.
- Return Eloquent models or `LengthAwarePaginator` — never raw arrays.

```php
// app/Repositories/Contracts/UserRepositoryInterface.php
namespace App\Repositories\Contracts;

use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

interface UserRepositoryInterface
{
    public function paginate(int $perPage = 15, array $with = []): LengthAwarePaginator;
    public function findById(int $id, array $with = []): ?User;
    public function findByEmail(string $email): ?User;
    public function create(array $data): User;
    public function update(User $user, array $data): User;
    public function delete(User $user): void;
}
```

```php
// app/Repositories/EloquentUserRepository.php
namespace App\Repositories;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class EloquentUserRepository implements UserRepositoryInterface
{
    public function paginate(int $perPage = 15, array $with = []): LengthAwarePaginator
    {
        return User::with($with)->latest()->paginate($perPage);
    }

    public function findById(int $id, array $with = []): ?User
    {
        return User::with($with)->find($id);
    }

    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    public function create(array $data): User
    {
        return User::create($data);
    }

    public function update(User $user, array $data): User
    {
        $user->update($data);

        return $user->refresh();
    }

    public function delete(User $user): void
    {
        $user->delete();
    }
}
```

---

### 6. Exceptions — `app/Exceptions/`

**Rules:**
- One class per domain error. Extend `\RuntimeException`.
- Add a `render(Request $request): JsonResponse` method — Laravel calls it automatically. **Do not** also register self-rendering exceptions in `bootstrap/app.php` (redundant).
- Only register non-renderable or third-party exceptions in `bootstrap/app.php`.
- All JSON error responses use the same envelope: `{ "message": "..." }`. For validation errors, Laravel's default `ValidationException` already uses `{ "message": "...", "errors": {...} }` — do not override it.

```php
// app/Exceptions/UserNotFoundException.php
namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserNotFoundException extends \RuntimeException
{
    public function __construct(int $id)
    {
        parent::__construct("User [{$id}] not found.");
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 404);
    }
}
```

Register **only** third-party/non-renderable exceptions in `bootstrap/app.php`:

```php
->withExceptions(function (Exceptions $exceptions): void {
    // Catch-all for JSON requests hitting an unhandled exception
    $exceptions->render(function (\Throwable $e, Request $request) {
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Server error.'], 500);
        }
    });
})
```

---

### 7. JSON Resources — `app/Http/Resources/`

**Rules:**
- Explicitly whitelist every field in `toArray()` — never return `parent::toArray()` or `$this->resource->toArray()`.
- Use `$this->whenLoaded('relation')` for relationships — prevents exposing unloaded data.
- Use `$this->created_at?->toISOString()` for all timestamps.
- Paginated responses: use `UserResource::collection($paginator)` — Laravel wraps automatically with `data`, `links`, and `meta`.

```php
// app/Http/Resources/UserResource.php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'email'      => $this->email,
            'posts'      => PostResource::collection($this->whenLoaded('posts')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
```

---

### 8. Binding — `app/Providers/AppServiceProvider.php`

All interface → concrete bindings go in `AppServiceProvider::register()`. This is the only place.

```php
public function register(): void
{
    $this->app->bind(
        \App\Repositories\Contracts\UserRepositoryInterface::class,
        \App\Repositories\EloquentUserRepository::class,
    );

    $this->app->bind(
        \App\Services\Contracts\UserServiceInterface::class,
        \App\Services\UserService::class,
    );
}
```

---

## SOLID in This Codebase

| Principle | Enforcement |
|---|---|
| **S** — Single Responsibility | Controller = HTTP only; Service = business logic only; Repository = DB only |
| **O** — Open/Closed | Swap implementations (e.g. cached repo) without touching the consumer |
| **L** — Liskov Substitution | `CachedUserRepository implements UserRepositoryInterface` drops in without changing `UserService` |
| **I** — Interface Segregation | Each interface is small and resource-specific — never one giant `RepositoryInterface` |
| **D** — Dependency Inversion | Controller → ServiceInterface; Service → RepositoryInterface; never depend on concretions |

---

## Adding a New Resource — Checklist

When adding any new resource (e.g. `Post`), create **all** in this order:

1. `php artisan make:model Post -m` — model + migration
2. Add `#[Fillable([...])]` attribute to the model (not `$fillable` array)
3. `app/Repositories/Contracts/PostRepositoryInterface.php`
4. `app/Repositories/EloquentPostRepository.php`
5. `app/Services/Contracts/PostServiceInterface.php`
6. `app/Services/PostService.php`
7. `app/Exceptions/PostNotFoundException.php`
8. `app/Http/Requests/Post/StorePostRequest.php` + `UpdatePostRequest.php`
9. `app/Http/Resources/PostResource.php`
10. `app/Http/Controllers/Api/V1/PostController.php`
11. Add `Route::apiResource('posts', PostController::class)` to `routes/api.php`
12. Bind both interfaces in `AppServiceProvider::register()`
