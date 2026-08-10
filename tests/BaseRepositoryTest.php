<?php

declare(strict_types=1);

use Darwinnatha\LaravelRepositoryPattern\Exceptions\RepositoryException;
use Darwinnatha\LaravelRepositoryPattern\Repositories\BaseRepository;
use Darwinnatha\LaravelRepositoryPattern\Tests\Fixtures\User;
use Darwinnatha\LaravelRepositoryPattern\Tests\Fixtures\UserRepository;

beforeEach(function (): void {
    $this->repository = new UserRepository();
});

it('can create a model instance', function (): void {
    $user = $this->repository->create([
        'name' => 'Darwin Nathan',
        'email' => 'darwin@example.com',
    ]);

    expect($user)->toBeInstanceOf(User::class)
        ->and($user->name)->toBe('Darwin Nathan')
        ->and($user->email)->toBe('darwin@example.com');

    $this->assertDatabaseHas('users', ['email' => 'darwin@example.com']);
});

it('can retrieve a model by id', function (): void {
    $createdUser = User::create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ]);

    $retrievedUser = $this->repository->retrieve($createdUser->id);

    expect($retrievedUser)->not->toBeNull()
        ->and($retrievedUser->id)->toBe($createdUser->id);
});

it('can update a model', function (): void {
    $user = User::create([
        'name' => 'Old Name',
        'email' => 'update@example.com',
    ]);

    $updatedUser = $this->repository->update($user->id, [
        'name' => 'New Name',
    ]);

    expect($updatedUser->name)->toBe('New Name');
    $this->assertDatabaseHas('users', ['name' => 'New Name']);
});

it('can soft delete and restore a model', function (): void {
    $user = User::create([
        'name' => 'Soft Delete User',
        'email' => 'delete@example.com',
    ]);

    // Delete
    $deleted = $this->repository->delete($user->id);
    expect($deleted)->toBeTrue();
    $this->assertSoftDeleted('users', ['id' => $user->id]);

    // Retrieve trashed
    $trashedUser = $this->repository->retrieve($user->id, withTrashed: true);
    expect($trashedUser)->not->toBeNull();

    // Restore
    $restored = $this->repository->restore($user);
    expect($restored)->toBeTrue();
    $this->assertDatabaseHas('users', ['id' => $user->id, 'deleted_at' => null]);
});

it('throws a RepositoryException if model class is not defined', function (): void {
    $invalidRepository = new class extends BaseRepository {};

    expect(fn () => $invalidRepository->all())
        ->toThrow(RepositoryException::class);
});
