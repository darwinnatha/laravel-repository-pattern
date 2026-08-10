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
     * Le nom et la signature de la commande Artisan.
     *
     * @var string
     */
    protected $signature = 'make:repository {name : Le nom du repository (ex: TransactionAssessment ou TransactionAssessmentRepository)}
                            {--m|model= : Le nom du modèle associé (par défaut déduit du nom du repository)}';

    /**
     * La description de la commande.
     *
     * @var string
     */
    protected $description = 'Génère l\'interface du contrat et l\'implémentation Eloquent du Repository';

    public function __construct(protected Filesystem $files)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $rawName = Str::studly($this->argument('name'));

        // Nettoyage pour obtenir la racine du nom (ex: TransactionAssessment)
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
            $this->components->info("Interface créee : [App/Repositories/Contracts/{$contractClass}.php]");
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
            $this->components->info("Implémentation Eloquent créee : [App/Repositories/Eloquent/{$eloquentClass}.php]");
        }

        return self::SUCCESS;
    }

    /**
     * Crée le fichier si celui-ci n'existe pas déjà.
     */
    protected function createClassFromStub(string $path, string $stubPath, array $replacements): bool
    {
        if ($this->files->exists($path)) {
            $this->components->warn("Le fichier existe déjà : [{$path}]");

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
     * Déduit le FQCN du modèle cible (App\Models\ModelName).
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
