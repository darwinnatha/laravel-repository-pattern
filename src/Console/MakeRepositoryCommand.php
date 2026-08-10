<?php

declare(strict_types=1);

/**
 * @author Darwin Fotso <fotsodarwin@gmail.com|https://x.com/fotso_darwin>
 */

namespace Darwinnatha\LaravelRepositoryPattern\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

final class MakeRepositoryCommand extends Command
{
    /**
     * Command name and signature
     *
     * @var string
     */
    protected $signature = 'make:repository {name : Repository name (ie: Customer or CustomerRepository)}
                            {--m|model= : Model name (default is derived from repository name)}';

    /**
     * Command description
     *
     * @var string
     */
    protected $description = 'Generate Repository interface and Eloquent implementation';

    public function __construct(protected Filesystem $files)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $rawName = Str::studly($this->argument('name'));

        // Clean up to get the base name (ex: Customer)
        $baseName = Str::replaceLast('Repository', '', $rawName);

        $modelName = $this->option('model')
            ? Str::studly($this->option('model'))
            : $baseName;

        $modelClass = $this->qualifyModel($modelName);

        $contractNamespace = 'App\\Repositories\\Contracts';
        $eloquentNamespace = 'App\\Repositories\\Eloquent';

        $contractClass = "{$baseName}Repository";
        $eloquentClass = "{$baseName}RepositoryEloquent";

        $contractPath = app_path("Repositories/Contracts/{$contractClass}.php");
        $eloquentPath = app_path("Repositories/Eloquent/{$eloquentClass}.php");

        // 1. Génération de l'Interface (Contract)
        if ($this->createClassFromStub($contractPath, __DIR__.'/../../stubs/repository.contract.stub', [
            '{{ contractNamespace }}' => $contractNamespace,
            '{{ contractClass }}' => $contractClass,
            '{{ modelClass }}' => $modelClass,
            '{{ modelName }}' => $modelName,
        ])) {
            $this->components->info("Interface created : [App/Repositories/Contracts/{$contractClass}.php]");
        }

        // 2. Génération de l'implémentation (Eloquent)
        if ($this->createClassFromStub($eloquentPath, __DIR__.'/../../stubs/repository.eloquent.stub', [
            '{{ eloquentNamespace }}' => $eloquentNamespace,
            '{{ eloquentClass }}' => $eloquentClass,
            '{{ contractNamespace }}' => $contractNamespace,
            '{{ contractClass }}' => $contractClass,
            '{{ modelClass }}' => $modelClass,
            '{{ modelName }}' => $modelName,
        ])) {
            $this->components->info("Eloquent implementation created : [App/Repositories/Eloquent/{$eloquentClass}.php]");
        }

        return self::SUCCESS;
    }

    /**
     * Creates the file if it does not already exist.
     */
    protected function createClassFromStub(string $path, string $stubPath, array $replacements): bool
    {
        if ($this->files->exists($path)) {
            $this->components->warn("The file already exists : [{$path}]");

            return false;
        }

        $this->files->ensureDirectoryExists(dirname($path));

        $stub = $this->files->get($stubPath);

        foreach ($replacements as $key => $value) {
            $stub = str_replace($key, $value, $stub);
        }

        $this->files->put($path, $stub);

        return true;
    }

    /**
     * Qualifies the model name to its FQCN (App\Models\ModelName).
     */
    protected function qualifyModel(string $model): string
    {
        $model = ltrim($model, '\\/');

        $model = str_replace('/', '\\', $model);

        $rootNamespace = $this->laravel->getNamespace();

        if (Str::startsWith($model, $rootNamespace)) {
            return $model;
        }

        return $rootNamespace.'Models\\'.$model;
    }
}
