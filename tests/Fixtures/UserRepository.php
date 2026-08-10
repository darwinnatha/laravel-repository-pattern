<?php

declare(strict_types=1);

namespace Darwinnatha\LaravelRepositoryPattern\Tests\Fixtures;

use Darwinnatha\LaravelRepositoryPattern\Repositories\BaseRepository;

final class UserRepository extends BaseRepository
{
    protected string $model = User::class;
}
