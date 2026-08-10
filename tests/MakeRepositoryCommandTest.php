<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    File::deleteDirectory(app_path('Repositories'));
});

it('generates contract and eloquent repository via artisan command', function (): void {
    $this->artisan('make:repository', ['name' => 'Product'])
        ->assertSuccessful();

    expect(app_path('Repositories/Contracts/ProductRepository.php'))->toBeFile()
        ->and(app_path('Repositories/Eloquent/ProductRepositoryEloquent.php'))->toBeFile();
});
