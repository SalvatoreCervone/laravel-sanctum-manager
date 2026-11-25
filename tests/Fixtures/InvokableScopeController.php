<?php

declare(strict_types=1);

namespace SanctumManager\Tests\Fixtures;

use Illuminate\Http\JsonResponse;
use SanctumManager\Attributes\RequiresScope;

#[RequiresScope('reports:export')]
class InvokableScopeController
{
    public function __invoke(): JsonResponse
    {
        return response()->json(['action' => 'invoke']);
    }
}
