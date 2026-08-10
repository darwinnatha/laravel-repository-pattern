# Laravel Repository Architecture Package

[![Latest Version on Packagist](https://img.shields.io/packagist/v/darwinnatha/laravel-repository-pattern.svg?style=flat-square)](https://packagist.org/packages/darwinnatha/laravel-repository-pattern)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/darwinnatha/laravel-repository-pattern/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/darwinnatha/laravel-repository-pattern/actions)
[![Total Downloads](https://img.shields.io/packagist/dt/darwinnatha/laravel-repository-pattern.svg?style=flat-square)](https://packagist.org/packages/darwinnatha/laravel-repository-pattern)
[![License](https://img.shields.io/packagist/l/darwinnatha/laravel-repository-pattern.svg?style=flat-square)](LICENSE.md)

An elegant, flexible, and type-safe Repository Pattern implementation for Eloquent models in Laravel. This package decouples your data layer from controllers, standardizes query methods, and includes auto-binding out of the box.

---

## Features

- 🎯 **Decoupled Architecture**: Easily separate data access logic from HTTP controllers.
- ⚡ **Auto-Binding Container**: Contracts and Eloquent implementations are automatically bound in Laravel's Service Container.
- 🛠 **Artisan Command CLI**: Generate repository contracts and implementations instantly using `php artisan make:repository`.
- 🔍 **Type-Safe & Generics Ready**: Full support for PHPStan/Larastan annotations (`@template TModel of Model`).
- 🔄 **Event & Soft Delete Controls**: Native support for query filtering, soft deletes, and bypassing model events.

---

## Installation

You can install the package via Composer:
```bash
composer require darwinnatha/laravel-repository-pattern
```
The Service Provider will automatically register itself using Laravel's Package Discovery.
(Optional) You can publish the generator stubs to customize the generated repository files:
```bash
php artisan vendor:publish --tag="repository-stubs"
```
## Quick Start
1. Generate a Repository

Run the Artisan command to create both the interface contract and the Eloquent implementation:
```bash
php artisan make:repository Customer
```
This creates two files in your application:
- `app/Repositories/Contracts/CustomerRepository.php`
- `app/Repositories/Eloquent/CustomerRepositoryEloquent.php`

2. Define Custom Methods (Optional)

In your Contract Interface:
```php
namespace App\Repositories\Contracts;

use App\Models\Customer;
use Darwinnatha\LaravelRepositoryPattern\Contracts\Repository;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends Repository<Customer>
 */
interface CustomerRepository extends Repository 
{
    /**
     * Finds the customer with the given phone number.
     *
     * @param string $phoneNumber the phone number to search for
     * @return ?Customer the customer with the given phone number, or null if not found
     */
    public function wherePhoneNumber(string $phoneNumber): ?Customer;
}
```

In your Eloquent Implementation:
```php
namespace App\Repositories\Eloquent;

use App\Models\Customer;
use App\Repositories\Contracts\CustomerRepository;
use Darwinnatha\LaravelRepositoryPattern\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * @extends BaseRepository<Customer>
 */
final class CustomerRepositoryEloquent extends BaseRepository implements CustomerRepository
{
    /**
     * The model associated with the repository.
     *
     * @var class-string<Customer>
     */
    protected string $model = Customer::class;

    /**
     * The default sort column for the repository.
     *
     * @var string
     */
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
            'relations' => ['credential', 'profile'],
        ]);
    }
    
    public function wherePhoneNumber(string $phoneNumber): ?Customer
    {
        return $this->model::whereRelation('profile', 'phone_number', $phoneNumber)->first();
    }
}
```

In the controller:
```php
namespace App\Http\Controllers;

use App\Repositories\Contracts\CustomerRepository;

class CustomerController extends Controller
{
    public function __construct(
        private CustomerRepository $customerRepository,
    ) {}

    public function withPhoneNumber(string $phoneNumber)
    {
        $customer = $this->customerRepository->wherePhoneNumber($phoneNumber);

        if (!$customer) {
            return response()->json(['message' => 'Customer not found'], 404);
        }

        return response()->json($customer);
    }
}
```
