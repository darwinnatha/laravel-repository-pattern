<?php

declare(strict_types=1);

namespace Darwinnatha\LaravelRepositoryPattern\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * User model for testing purposes.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 */
final class User extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'email'];
}
