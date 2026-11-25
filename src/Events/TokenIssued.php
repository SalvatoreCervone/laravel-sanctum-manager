<?php

declare(strict_types=1);

namespace SanctumManager\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use SanctumManager\Models\PersonalAccessToken;

class TokenIssued
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  array<int, string>  $scopes
     */
    public function __construct(
        public Model $tokenable,
        public PersonalAccessToken $accessToken,
        public array $scopes = [],
    ) {}
}
