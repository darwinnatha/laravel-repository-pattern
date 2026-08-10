<?php

declare(strict_types=1);

namespace Darwinnatha\LaravelRepositoryPattern\Exceptions;

use Exception;

final class RepositoryException extends Exception
{
    /**
     * Déclenché quand la propriété $model n'est pas définie sur le Repository.
     */
    public static function modelNotDefined(string $repositoryClass): self
    {
        return new self("The \$model property must be defined in the repository [{$repositoryClass}].");
    }

    /**
     * Déclenché quand la classe spécifiée dans $model n'existe pas.
     */
    public static function modelClassDoesNotExist(string $modelClass, string $repositoryClass): self
    {
        return new self("The model class [{$modelClass}] specified in [{$repositoryClass}] does not exist.");
    }

    /**
     * Déclenché quand la classe $model n'hérite pas de Illuminate\Database\Eloquent\Model.
     */
    public static function invalidModelClass(string $modelClass, string $repositoryClass): self
    {
        return new self("The model class [{$modelClass}] specified in [{$repositoryClass}] must be an instance of Illuminate\\Database\\Eloquent\\Model.");
    }
}
