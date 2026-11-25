<?php

declare(strict_types=1);

namespace SanctumManager\DataTransferObjects;

use Carbon\CarbonInterface;
use Laravel\Sanctum\NewAccessToken;
use SanctumManager\Models\PersonalAccessToken;

readonly class RotatedTokenResult
{
    public function __construct(
        public NewAccessToken $newToken,
        public PersonalAccessToken $oldToken,
        public CarbonInterface $graceExpiresAt,
    ) {}
}
