<?php

declare(strict_types=1);

namespace SanctumManager\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'scopes' => ['sometimes', 'array'],
            'scopes.*' => ['string'],
            'expires_in_minutes' => ['nullable', 'integer', 'min:1'],
            'allowed_ips' => ['nullable', 'array'],
            'allowed_ips.*' => ['string'],
            'allowed_origins' => ['nullable', 'array'],
            'allowed_origins.*' => ['string'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
