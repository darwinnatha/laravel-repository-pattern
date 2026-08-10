<?php

declare(strict_types=1);

/**
 * @author Darwin Fotso <fotsodarwin@gmail.com|https://x.com/fotso_darwin>
 */

namespace Darwinnatha\LaravelRepositoryPattern\Repositories;

use AllowDynamicProperties;
use BadMethodCallException;
use Darwinnatha\LaravelRepositoryPattern\Contracts\Repository;
use Darwinnatha\LaravelRepositoryPattern\Exceptions\RepositoryException;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\Exceptions\InvalidFilterQuery;
use Spatie\QueryBuilder\Exceptions\InvalidIncludeQuery;
use Spatie\QueryBuilder\Exceptions\InvalidSortQuery;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Base repository class for Zeney models.
 *
 * @template TModel of Model
 *
 * @implements Repository<TModel>
 */
#[AllowDynamicProperties]
abstract class BaseRepository implements Repository
{
    /**
     * Ignores soft-deleted records.
     */
    private const string TRASH_MODE_IGNORE = 'ignore';

    /**
     * Only returns soft-deleted records.
     */
    private const string TRASH_MODE_ONLY = 'only';

    /**
     * Includes soft-deleted records.
     */
    private const string TRASH_MODE_WITH = 'with';

    /**
     * Default sort column for retrieved models.
     */
    protected string $defaultSort = 'id';

    /**
     * Class name of the model this repository manages.
     *
     * @var class-string<TModel>
     */
    protected string $model;

    /**
     * Repository configuration.
     *
     * @var array{
     *   filters: list<AllowedFilter|string>,
     *   includes: array<string>,
     *   sorts: array<string>,
     *   relations: array<string>
     * }
     */
    protected array $config = [
        'filters' => [],
        'includes' => [],
        'sorts' => [],
        'relations' => [],
    ];

    public function __construct(
        array $config = [],
    ) {
        $this->config = array_merge_recursive([
            'filters' => [],
            'includes' => [],
            'sorts' => [],
            'relations' => [],
        ], $this->config, $config);
    }

    /**
     * Allows to call scoped queries from models.
     *
     * @param string $method the name of the scope to apply
     * @param array $args array containing all the arguments passed to the scope
     *
     * @throws BindingResolutionException
     */
    public function __call(string $method, array $args)
    {
        if ((new $this->model)->hasNamedScope($method)) {
            return $this->buildQuery()->{$method}(...$args);
        }

        throw new BadMethodCallException("There is no scope with name '$method' in the model.");
    }

    /**
     * Builds the Eloquent query builder from the HTTP request and the repository config.
     *
     * @param 'ignore'|'only'|'with' $trashMode how to handle soft-deleted records
     *
     * @return QueryBuilder<TModel>
     *
     * @throws InvalidFilterQuery
     * @throws InvalidIncludeQuery
     * @throws InvalidSortQuery
     */
    protected function buildQuery(string $trashMode = self::TRASH_MODE_IGNORE): QueryBuilder
    {
        if (! isset($this->model) || empty($this->model)) {
            throw RepositoryException::modelNotDefined(static::class);
        }

        if (! class_exists($this->model)) {
            throw RepositoryException::modelClassDoesNotExist($this->model, static::class);
        }

        if (! is_subclass_of($this->model, Model::class)) {
            throw RepositoryException::invalidModelClass($this->model, static::class);
        }

        /** @var QueryBuilder<TModel> $query */
        $query = QueryBuilder::for($this->model)
            ->allowedFilters($this->config['filters'])
            ->allowedIncludes($this->config['includes'])
            ->allowedSorts($this->config['sorts'])
            ->defaultSort($this->defaultSort)
            ->with($this->config['relations']);

        if ($this->supportsSoftDeletes()) {
            if ($trashMode === self::TRASH_MODE_WITH) {
                $query->withTrashed();
            } elseif ($trashMode === self::TRASH_MODE_ONLY) {
                $query->onlyTrashed();
            }
        }

        return $query;
    }

    /**
     * @inheritDoc
     *
     * @throws BindingResolutionException
     */
    public function all(array $queries = []): Collection|Paginator
    {
        return $this->handleMaybePaginatedQuery(fn () => $this->buildQuery(self::TRASH_MODE_IGNORE), $queries);
    }

    /**
     * @inheritDoc
     *
     * @throws BindingResolutionException
     */
    public function trashed(array $queries = []): Collection|Paginator
    {
        return $this->handleMaybePaginatedQuery(fn () => $this->buildQuery(self::TRASH_MODE_ONLY), $queries);
    }

    /**
     * @inheritDoc
     *
     * @throws BindingResolutionException
     */
    public function retrieve(string|int $id, bool $withTrashed = false): ?Model
    {
        return $this->buildQuery($withTrashed ? self::TRASH_MODE_WITH : self::TRASH_MODE_IGNORE)->find($id);
    }

    /**
     * @inheritDoc
     *
     * @throws BindingResolutionException
     */
    public function collect(array $ids, bool $withTrashed = false): Collection
    {
        if (empty($ids)) {
            return new Collection;
        }

        return $this->buildQuery($withTrashed ? self::TRASH_MODE_WITH : self::TRASH_MODE_IGNORE)
            ->whereIn('id', $ids)
            ->get();
    }

    /**
     * @inheritDoc
     *
     * @throws BindingResolutionException
     */
    public function where(array $conditions): Collection
    {
        return $this->buildQuery(self::TRASH_MODE_IGNORE)->where($conditions)->get();
    }

    /**
     * @inheritDoc
     *
     * @throws BindingResolutionException
     */
    public function whereFirst(array $conditions): ?Model
    {
        return $this->buildQuery(self::TRASH_MODE_IGNORE)->where($conditions)->first();
    }

    /**
     * @inheritDoc
     *
     * @throws BindingResolutionException
     */
    public function whereFirstOrFail(array $conditions): Model
    {
        return $this->buildQuery(self::TRASH_MODE_IGNORE)->where($conditions)->firstOrFail();
    }

    /**
     * @inheritDoc
     */
    public function create(array $attributes, bool $withoutEvents = false): ?Model
    {
        /** @var TModel|null $model */
        $model = $this->handleQueryWithoutEvents(fn () => $this->model::query()->create($attributes), $withoutEvents);

        if ($model && count($this->config['relations']) > 0) {
            $model->load($this->config['relations']);
        }

        return $model;
    }

    /**
     * @inheritDoc
     */
    public function update(string|int|Model $id, array $attributes, bool $withoutEvents = false): Model
    {
        $id = $this->ensureModel($id);

        $this->handleQueryWithoutEvents(fn () => $id->update($attributes), $withoutEvents);

        if (count($this->config['relations']) > 0) {
            $id->load($this->config['relations']);
        }

        return $id->refresh();
    }

    /**
     * @inheritDoc
     *
     * @throws BindingResolutionException
     */
    public function createOrUpdate(array $conditions, array $attributes, bool $withoutEvents = false): Model
    {
        $existingModel = $this->whereFirst($conditions);

        if ($existingModel) {
            return $this->update($existingModel, $attributes, $withoutEvents);
        }

        $mergedAttributes = array_merge($conditions, $attributes);

        return $this->create($mergedAttributes, $withoutEvents);
    }

    /**
     * @inheritDoc
     */
    public function delete(string|int|Model $id, bool $withoutEvents = false): bool
    {
        $id = $this->ensureModel($id);

        return $this->handleQueryWithoutEvents(fn () => (bool) $id->delete(), $withoutEvents);
    }

    /**
     * @inheritDoc
     */
    public function restore(string|int|Model $id, bool $withoutEvents = false): bool
    {
        if (! $this->supportsSoftDeletes()) {
            return false;
        }

        /** @var TModel&SoftDeletes $id */
        $id = $this->ensureModel($id);

        return $this->handleQueryWithoutEvents(fn () => $id->restore(), $withoutEvents);
    }

    /**
     * @inheritDoc
     */
    public function forceDelete(string|int|Model $id, bool $withoutEvents = false): bool
    {
        $id = $this->ensureModel($id);

        return $this->handleQueryWithoutEvents(fn () => $id->forceDelete(), $withoutEvents);
    }

    /**
     * Retrieve a list of models that may be paginated.
     *
     * @param callable $query the query to execute
     * @param array $queries HTTP query parameters
     *
     * @throws BindingResolutionException
     * @throws InvalidFilterQuery
     * @throws InvalidIncludeQuery
     * @throws InvalidSortQuery
     */
    protected function handleMaybePaginatedQuery(callable $query, array $queries): Collection|Paginator
    {
        if (empty($queries)) {
            return $query()->get();
        }

        $paginate = (int) Arr::get($queries, 'paginate', 0);
        $columns = $this->getSelectedFields($queries);

        if ($paginate > 0) {
            return $query()
                ->fastPaginate($paginate, $columns)
                ->appends($queries);
        }

        return $query()->get($columns);
    }

    /**
     * Ensure to have a model instance if the given value is a string (model ID).
     *
     * @param string|TModel $model
     *
     * @return TModel
     *
     * @throws ModelNotFoundException
     */
    protected function ensureModel(string|int|Model $model): Model
    {
        return (is_string($model) || is_int($model)) ? $this->model::query()->findOrFail($model) : $model;
    }

    /**
     * Gets the list of fields to select from the model.
     *
     * @param array $queries HTTP query parameters
     *
     * @return array the final list of fields to select
     */
    private function getSelectedFields(array $queries): array
    {
        $fields = Arr::get($queries, 'attributes');

        if (empty($fields)) {
            return ['*'];
        }

        $fields = collect(explode(',', $fields));
        $model = new $this->model;

        $fields = $fields->where(fn ($field) => in_array($field, $model->getFillable(), true));

        if ($fields->isEmpty()) {
            return ['*'];
        }

        return ['id', ...$fields];
    }

    /**
     * Handles the query without events.
     *
     * @param callable $query the query to handle
     * @param bool $withoutEvents whether to handle the query without events
     *
     * @return mixed the result of the query
     */
    private function handleQueryWithoutEvents(callable $query, bool $withoutEvents = false): mixed
    {
        return $withoutEvents ? $this->model::withoutEvents($query) : $query();
    }

    /**
     * Checks if the managed model supports soft deletes.
     */
    private function supportsSoftDeletes(): bool
    {
        return in_array(SoftDeletes::class, class_uses_recursive($this->model), true);
    }
}
