<?php

declare(strict_types=1);

namespace SanctumManager\Contracts;

use Illuminate\Http\Request;
use SanctumManager\Models\PersonalAccessToken;

interface TokenUsageLoggerContract
{
    /**
     * Record token usage, enforcing max-use limits, debounced timestamp updates,
     * and optional request audit logging.
     */
    public function recordUsage(PersonalAccessToken $token, Request $request): void;
}
