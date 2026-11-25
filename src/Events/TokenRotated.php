<?php

declare(strict_types=1);

namespace SanctumManager\Events;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use SanctumManager\Models\PersonalAccessToken;

class TokenRotated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Model $tokenable,
        public PersonalAccessToken $oldToken,
        public PersonalAccessToken $newToken,
        public CarbonInterface $graceExpiresAt,
    ) {}
}
