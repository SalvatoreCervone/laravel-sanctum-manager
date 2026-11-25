<?php

declare(strict_types=1);

namespace SanctumManager\Tests\Fixtures;

use Illuminate\Http\JsonResponse;
use SanctumManager\Attributes\RequiresAnyScope;
use SanctumManager\Attributes\RequiresScope;

#[RequiresScope('orders:read')]
class SampleScopeController
{
    public function index(): JsonResponse
    {
        return response()->json(['action' => 'index']);
    }

    #[RequiresScope('orders:write', 'billing:charge')]
    public function store(): JsonResponse
    {
        return response()->json(['action' => 'store']);
    }

    #[RequiresAnyScope(['orders:cancel', 'orders:admin'])]
    public function destroy(): JsonResponse
    {
        return response()->json(['action' => 'destroy']);
    }
}
