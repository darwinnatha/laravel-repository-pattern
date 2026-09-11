# Laravel Repository Architecture Package

[![Latest Version on Packagist](https://img.shields.io/packagist/v/darwinnatha/laravel-repository-pattern.svg?style=flat-square)](https://packagist.org/packages/darwinnatha/laravel-repository-pattern)
[![Total Downloads](https://img.shields.io/packagist/dt/darwinnatha/laravel-repository-pattern.svg?style=flat-square)](https://packagist.org/packages/darwinnatha/laravel-repository-pattern)
[![License](https://img.shields.io/packagist/l/darwinnatha/laravel-repository-pattern.svg?style=flat-square)](LICENSE.md)

A modern, robust, and type-safe Repository Pattern implementation for Laravel applications. Designed for API-first architecture, this package decouples your data layer, standardizes URL filtering/eager-loading, and enforces clean separation of concerns.

---

## Key Features

- **Zero-Config Auto-Binding**: Interface contracts are automatically bound to Eloquent implementations in Laravel's Service Container.
- **Artisan Generator CLI**: Instantly scaffold Repository contracts and implementations using `php artisan make:repository`.
- **Declarative API Querying**: Configure HTTP `filters`, `includes`, `sorts`, and eager-loaded `relations` directly in the constructor.
- **Native Pagination**: Seamlessly switch between collections and paginated results (`['paginate' => 15]`).
- **Type-Safety & Generics**: Full PHPStan / Larastan support (`@template TModel of Model`) for perfect IDE autocomplete.
- **Event Bypassing**: Execute write/delete operations with or without triggering Eloquent model events.
- **Full Soft Delete Support**: Unified handling for soft-deleted and trashed records (`retrieve`, `collect`, `restore`, `forceDelete`).

---

## Installation

Install the package via Composer:

```bash
composer require darwinnatha/laravel-repository-pattern
```

---

## Quick Start & CLI Usage

Generate a contract interface and its Eloquent implementation in one command:

```bash
php artisan make:repository Customer
```

Generates:

* `app/Repositories/Contracts/CustomerRepository.php`
* `app/Repositories/Eloquent/CustomerRepositoryEloquent.php`

---

## 1. Custom Methods (Contract vs Implementation)

To ensure strict typing and allow your controllers to call specific methods directly via dependency injection, any custom method added to the Eloquent Repository must first be defined in its Interface (Contract).

### Step 1: Define the Method in the Contract (`CustomerRepository.php`)

```php
<?php

namespace App\Repositories\Contracts;

use App\Models\Customer;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Collection;
use Darwinnatha\LaravelRepositoryPattern\Contracts\Repository;

/**
 * @extends Repository<Customer>
 */
interface CustomerRepository extends Repository
{
    /**
     * Search for a customer by phone number.
     */
    public function wherePhoneNumber(string $phoneNumber): ?Customer;

    /**
     * Get a list of active verified customers.
     *
     * @return Collection<int, Customer>|Paginator<int, Customer>
     */
    public function getActiveVerifiedCustomers(): Collection|Paginator;
}
```

### Step 2: Implement the Method in Eloquent (`CustomerRepositoryEloquent.php`)

```php
<?php

namespace App\Repositories\Eloquent;

use App\Models\Customer;
use App\Repositories\Contracts\CustomerRepository;
use Darwinnatha\LaravelRepositoryPattern\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<Customer>
 */
final class CustomerRepositoryEloquent extends BaseRepository implements CustomerRepository
{
    protected string $model = Customer::class;

    public function wherePhoneNumber(string $phoneNumber): ?Customer
    {
        return $this->model::whereRelation('profile', 'phone_number', $phoneNumber)->first();
    }

    public function getActiveVerifiedCustomers(): Collection
    {
        return $this->query()
            ->where('is_active', true)
            ->whereNotNull('email_verified_at')
            ->get();
    }
}
```

Alternative with pagination:

```php
<?php

namespace App\Repositories\Eloquent;

use App\Models\Customer;
use App\Repositories\Contracts\CustomerRepository;
use Darwinnatha\LaravelRepositoryPattern\Repositories\BaseRepository;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<Customer>
 */
final class CustomerRepositoryEloquent extends BaseRepository implements CustomerRepository
{
    protected string $model = Customer::class;

    public function wherePhoneNumber(string $phoneNumber): ?Customer
    {
        return $this->model::whereRelation('profile', 'phone_number', $phoneNumber)->first();
    }

    public function getActiveVerifiedCustomers(): Collection|Paginator
    {
        return $this->handleMaybePaginatedQuery(fn() => $this->buildQuery()
            ->where('is_active', true)
            ->whereNotNull('email_verified_at'),
            request()->query()
        );
    }
}
```

### Step 3: Direct Call from Controller

The interface `CustomerRepository` provides direct access to autocompletion for standard and custom methods:

```php
<?php

namespace App\Http\Controllers;

use App\Repositories\Contracts\CustomerRepository;
use Illuminate\Http\JsonResponse;

class CustomerController extends Controller
{
    public function __construct(
        private readonly CustomerRepository $customerRepository
    ) {}

    public function findByPhone(string $phone): JsonResponse
    {
        // Fluent call to the specific method declared in the interface
        $customer = $this->customerRepository->wherePhoneNumber($phone);

        return response()->json($customer);
    }
}
```

---

## Declarative API Querying (Pagination, Filters, Includes, Sorts & Relations)

Instead of manually building query builders, configure allowed API query parameters directly within the repository's constructor:

```php
<?php

namespace App\Repositories\Eloquent;

use App\Models\Customer;
use App\Repositories\Contracts\CustomerRepository;
use Darwinnatha\LaravelRepositoryPattern\Repositories\BaseRepository;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * @extends BaseRepository<Customer>
 */
final class CustomerRepositoryEloquent extends BaseRepository implements CustomerRepository
{
    protected string $model = Customer::class;
    
    protected string $defaultSort = '-id';

    public function __construct()
    {
        parent::__construct([
            'filters' => [
                AllowedFilter::scope('search'),
                AllowedFilter::partial('city', 'addresses.city'),
                AllowedFilter::partial('country', 'country.name'),
                AllowedFilter::exact('country_id'),
                AllowedFilter::exact('is_active'),
            ],
            'includes' => ['country', 'trusted_devices'],
            'sorts' => ['id', 'email_verified_at'],
            'relations' => ['credential', 'profile'], // Automatically eager-loaded on every query
        ]);
    }

    public function wherePhoneNumber(string $phoneNumber): ?Customer
    {
        return $this->model::whereRelation('profile', 'phone_number', $phoneNumber)->first();
    }
}
```

---

## HTTP Query Parameter Usage

Once configured in your repository, frontend clients and API consumers can dynamically filter, include relationships, and sort data straight from the URL parameters:

### 1. Pagination (`?paginate` & `?page`)

Pagination is automatically handled by the `all()` and `trashed()` methods of the repository when the `paginate` parameter is passed.

```php
<?php

public function index(Request $request): JsonResponse
{
    // Take the number of items per page from the query parameter, or default to 15
    $customers = $this->customerRepository->all([
        'paginate' => $request->query('paginate', 15),
    ]);

    return response()->json($customers);
}
```

API clients can control the page size and navigate between pages via native Laravel URL query parameters:

```http
# Activate pagination with 10 items per page (default page is 1)
GET /api/customers?paginate=10

# Navigate to page 3 with 10 items per page
GET /api/customers?paginate=10&page=3

# Combine pagination with filters, sorting, and eager-loading
GET /api/customers?filter[is_active]=1&include=country&sort=-id&paginate=20&page=2
```

### 2. Filtering (`?filter[...]`)

Filter resources using exact matches, partial string searches, or model scopes:

```http
# Single filter
GET /api/customers?filter[is_active]=1

# Multiple criteria filtering
GET /api/customers?filter[country_id]=5&filter[city]=Douala&filter[search]=John
```

### 3. Including Relationships (`?include=...`)

Dynamically eager-load allowed relationships specified in the `'includes'` array:

```http
# Include allowed relationships
GET /api/customers?include=country,trusted_devices
```

### 4. Sorting (`?sort=...`)

Sort results dynamically using attributes defined in the `'sorts'` array (prefix with `-` for descending order):

```http
# Sort by email verification date ascending, then ID descending
GET /api/customers?sort=email_verified_at,-id
```

### 5. Combining Query Parameters

Combine pagination, filtering, eager-loading, and sorting in a single request:

```http
GET /api/customers?filter[is_active]=1&include=country&sort=-id&paginate=20&page=2
```

---

## Architectural Guidelines & Best Practices

### 1. Auto Eager-Loaded Relations (`relations`)

The `'relations'` array automatically appends relations to **every** query executed by the repository (via `$query->with(...)`). Use this for core data that the model always depends on (e.g., `profile` or `credential`).

### 2. Controller vs. Service Layer Separation (CQRS / Actions)

* **In Controllers**: Repositories should be injected to handle HTTP requests, pagination, and API parameter parsing. If an API request attempts to use unregistered query filters or relationships, an exception will be raised, keeping your API strict and secure.
* **In Services / Actions / Commands (CQRS)**: For complex business logic, cross-repository operations, or background jobs, encapsulate repository calls inside dedicated **Service Classes**, **Actions**, or **Query Handlers** rather than chaining multiple repositories inside a single controller.

```
┌────────────────────────┐
│     HTTP Controller    │ ◄── Handles Request & Parses API Queries
└───────────┬────────────┘
            │
            ▼
┌────────────────────────┐
│     Service / Action   │ ◄── Encapsulates Business Logic & Multi-Repo Orchestration
└───────────┬────────────┘
            │
            ▼
┌────────────────────────┐
│   CustomerRepository   │ ◄── Database Access Layer (Type-Safe & Isolated)
└────────────────────────┘
```

---

## Complete API Reference

| Method | Parameters | Return Type | Description |
| --- | --- | --- | --- |
| `all()` | `array $queries = []` | `Collection\|Paginator` | Retrieves all models. Supports `['paginate' => 15]`. |
| `trashed()` | `array $queries = []` | `Collection\|Paginator` | Retrieves all soft-deleted models. Supports `['paginate' => 15]`. |
| `retrieve()` | `string|int $id, bool $withTrashed = false` | `?Model` | Finds a single model by primary key. |
| `collect()` | `array $ids, bool $withTrashed = false` | `Collection` | Retrieves models matching an array of IDs. |
| `where()` | `array $conditions` | `Collection` | Retrieves models matching a condition array. |
| `whereFirst()` | `array $conditions` | `?Model` | Retrieves first matching model or `null`. |
| `whereFirstOrFail()` | `array $conditions` | `Model` | Retrieves first matching model or throws 404. |
| `create()` | `array $attributes, bool $withoutEvents = false` | `?Model` | Creates a model instance. |
| `update()` | `string|int|Model $id, array $attributes, bool $withoutEvents = false` | `Model` | Updates an existing model. |
| `createOrUpdate()` | `array $conditions, array $attributes, bool $withoutEvents = false` | `Model` | Executes an `updateOrCreate` operation. |
| `delete()` | `string\|int\|Model $id, bool $withoutEvents = false` | `bool` | Deletes a model by ID or instance. |
| `restore()` | `string\|int\|Model $id, bool $withoutEvents = false` | `bool` | Restores a soft-deleted model. |
| `forceDelete()` | `string\|int\|Model $id, bool $withoutEvents = false` | `bool` | Permanently deletes a model. |

---

## Testing & Quality Assurance

```bash
# Run Pest tests
composer test
# Run Larastan static analysis
composer analyse ./src
# Format code with Laravel Pint
composer format --config pint.json
```

---

## License

The MIT License (MIT). Please see [LICENSE.md](LICENSE.md) for more information.
