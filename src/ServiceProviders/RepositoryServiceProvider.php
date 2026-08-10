<?php

declare(strict_types=1);

/**
 * @author Darwin Fotso <fotsodarwin@gmail.com|https://x.com/fotso_darwin>
 */

namespace Darwinnatha\LaravelRepositoryPattern\ServiceProviders;

use Darwinnatha\LaravelRepositoryPattern\Console\MakeRepositoryCommand;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Finder\Finder;

final class RepositoryServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                MakeRepositoryCommand::class,
            ]);

            // Permet de publier les stubs si l'utilisateur souhaite les personnaliser
            $this->publishes([
                __DIR__.'/../../stubs' => base_path('stubs/vendor/laravel-repository'),
            ], 'repository-stubs');
        }
    }

    public function register(): void
    {
        $this->autoBindRepositories();
    }

    protected function autoBindRepositories(): void
    {
        $contractsPath = app_path('Repositories/Contracts');

        if (! file_exists($contractsPath)) {
            return;
        }

        $finder = new Finder;
        $finder->files()->in($contractsPath)->name('*Repository.php');

        foreach ($finder as $file) {
            $interfaceName = $file->getBasename('.php');
            $interface = "App\\Repositories\\Contracts\\{$interfaceName}";

            $implementation = "App\\Repositories\\Eloquent\\{$interfaceName}Eloquent";

            if (interface_exists($interface) && class_exists($implementation)) {
                $this->app->singleton($interface, $implementation);
            }
        }
    }
}
