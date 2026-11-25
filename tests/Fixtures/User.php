<?php

declare(strict_types=1);

namespace SanctumManager\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;
use SanctumManager\Traits\ManagesSanctumTokens;

class User extends Authenticatable
{
    use ManagesSanctumTokens;

    protected $table = 'users';

    protected $guarded = [];
}
