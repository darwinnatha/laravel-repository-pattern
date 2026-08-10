<?php

declare(strict_types=1);

/**
 * @author Darwin Fotso <fotsodarwin@gmail.com|https://x.com/fotso_darwin>
 */

namespace Darwinnatha\LaravelRepositoryPattern\Contracts;

use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Repository interface.
 *
 * @template TModel of Model
 */
interface Repository
{
    /**
     * Retrieve all models.
     *
     * @param array<string, mixed> $queries HTTP query parameters
     *
     * @return Collection<int, TModel>|Paginator<int, TModel>
     */
    public function all(array $queries = []): Collection|Paginator;

    /**
     * Retrieve all trashed models.
     *
     * @param array<string, mixed> $queries HTTP query parameters
     *
     * @return Collection<int, TModel>|Paginator<int, TModel>
     */
    public function trashed(array $queries = []): Collection|Paginator;

    /**
     * Retrieve a single model with the given ID.
     *
     * @param string $id the ID of the model to retrieve
     * @param bool $withTrashed whether to include trashed models
     *
     * @return TModel|null
     */
    public function retrieve(string $id, bool $withTrashed = false): ?Model;

    /**
     * Retrieve multiple models with the given IDs.
     *
     * @param list<string> $ids the IDs of the models to retrieve
     *
     * @return Collection<int, TModel>
     */
    public function collect(array $ids, bool $withTrashed = false): Collection;

    /**
     * Retrieve the models matching the given conditions.
     *
     * @param array<string, mixed> $conditions the conditions to match
     *
     * @return Collection<int, TModel>
     */
    public function where(array $conditions): Collection;

    /**
     * Retrieve the first model matching the given conditions.
     *
     * @param array<string, mixed> $conditions the conditions to match
     *
     * @return TModel|null
     */
    public function whereFirst(array $conditions): ?Model;

    /**
     * Retrieve the first model matching the given conditions or fail.
     *
     * @param array<string, mixed> $conditions the conditions to match
     *
     * @return TModel
     */
    public function whereFirstOrFail(array $conditions): Model;

    /**
     * Create a new model instance.
     *
     * @param array<string, mixed> $attributes the attributes to set on the model
     * @param bool $withoutEvents whether to not fire events
     *
     * @return TModel|null
     */
    public function create(array $attributes, bool $withoutEvents = false): ?Model;

    /**
     * Update a model with the given ID.
     *
     * @param string|TModel $id the ID of the model to update
     * @param array<string, mixed> $attributes the attributes to set on the model
     * @param bool $withoutEvents whether to not fire events
     *
     * @return TModel
     */
    public function update(string|Model $id, array $attributes, bool $withoutEvents = false): Model;

    /**
     * Create a new model or update an existing one based on the given conditions.
     *
     * @param array<string, mixed> $conditions the conditions to search for an existing model
     * @param array<string, mixed> $attributes the attributes to set on the model
     * @param bool $withoutEvents whether to not fire events
     *
     * @return TModel
     */
    public function createOrUpdate(array $conditions, array $attributes, bool $withoutEvents = false): Model;

    /**
     * Delete a model with the given ID.
     *
     * @param string|TModel $id the ID of the model to delete
     * @param bool $withoutEvents whether to not fire events
     */
    public function delete(string|Model $id, bool $withoutEvents = false): bool;

    /**
     * Restore a model with the given ID.
     *
     * @param string|TModel $id the ID of the model to restore
     * @param bool $withoutEvents whether to not fire events
     */
    public function restore(string|Model $id, bool $withoutEvents = false): bool;

    /**
     * Force delete a model with the given ID.
     *
     * @param string|TModel $id the ID of the model to force delete
     * @param bool $withoutEvents whether to not fire events
     */
    public function forceDelete(string|Model $id, bool $withoutEvents = false): bool;
}
