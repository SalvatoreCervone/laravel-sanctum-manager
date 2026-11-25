<?php

declare(strict_types=1);

namespace SanctumManager\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;
use SanctumManager\Models\PersonalAccessToken;

class TokenIpDenied
{
    use Dispatchable;

    public function __construct(
        public ?Model $tokenable,
        public PersonalAccessToken $token,
        public ?string $clientIp,
        public Request $request,
    ) {}
}
