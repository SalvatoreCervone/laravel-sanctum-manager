<?php

declare(strict_types=1);

namespace SanctumManager\DataTransferObjects;

readonly class ScopeDefinition
{
    /**
     * @param  array<int, string>  $implies
     */
    public function __construct(
        public string $name,
        public string $description = '',
        public ?string $group = null,
        public array $implies = [],
        public bool $isDefault = false,
        public bool $isDeprecated = false,
    ) {}

    /**
     * @return array{
     *     name: string,
     *     description: string,
     *     group: ?string,
     *     implies: array<int, string>,
     *     is_default: bool,
     *     is_deprecated: bool
     * }
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'group' => $this->group,
            'implies' => $this->implies,
            'is_default' => $this->isDefault,
            'is_deprecated' => $this->isDeprecated,
        ];
    }
}
