<?php

declare(strict_types=1);

namespace SanctumManager\DataTransferObjects;

readonly class ScopeGroup
{
    /**
     * @param  array<int, string>  $scopes
     */
    public function __construct(
        public string $name,
        public array $scopes,
        public string $description = '',
    ) {}

    /**
     * @return array{
     *     name: string,
     *     scopes: array<int, string>,
     *     description: string
     * }
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'scopes' => $this->scopes,
            'description' => $this->description,
        ];
    }
}
