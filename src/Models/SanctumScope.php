<?php

declare(strict_types=1);

namespace SanctumManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use SanctumManager\DataTransferObjects\ScopeDefinition;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string|null $group
 * @property array<int, string>|null $implies
 * @property bool $is_default
 * @property bool $is_deprecated
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SanctumScope extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'sanctum_scopes';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'description',
        'group',
        'implies',
        'is_default',
        'is_deprecated',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'implies' => 'array',
        'is_default' => 'boolean',
        'is_deprecated' => 'boolean',
    ];

    /**
     * Convert the persisted scope model into a ScopeDefinition DTO.
     */
    public function toDefinition(): ScopeDefinition
    {
        /** @var array<int, mixed> $rawImplies */
        $rawImplies = is_array($this->implies) ? $this->implies : [];

        $implies = array_values(array_filter(
            $rawImplies,
            static fn (mixed $item): bool => is_string($item),
        ));

        return new ScopeDefinition(
            name: $this->name,
            description: $this->description ?? '',
            group: $this->group,
            implies: $implies,
            isDefault: (bool) $this->is_default,
            isDeprecated: (bool) $this->is_deprecated,
        );
    }
}
