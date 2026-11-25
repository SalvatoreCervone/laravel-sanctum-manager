<?php

declare(strict_types=1);

namespace SanctumManager\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;
use SanctumManager\Models\PersonalAccessToken;

class TokenScopeDenied
{
    use Dispatchable;

    /**
     * @param  array<int, string>  $requiredScopes
     */
    public function __construct(
        public ?Model $tokenable,
        public PersonalAccessToken $token,
        public array $requiredScopes,
        public Request $request,
    ) {}
}
