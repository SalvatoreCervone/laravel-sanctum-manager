<?php

declare(strict_types=1);

namespace SanctumManager\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use SanctumManager\Models\PersonalAccessToken;

class TokenRevoked
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public ?Model $tokenable,
        public PersonalAccessToken $token,
        public ?string $reason = null,
    ) {}
}
