<?php

declare(strict_types=1);

namespace SanctumManager\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RotateTokenRequest extends FormRequest
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
            'grace_period_minutes' => ['nullable', 'integer', 'min:0'],
            'name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
