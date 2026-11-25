<?php

declare(strict_types=1);

namespace SanctumManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use SanctumManager\DataTransferObjects\ScopeGroup;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property array<int, string>|null $scopes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SanctumScopeGroup extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'sanctum_scope_groups';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'description',
        'scopes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'scopes' => 'array',
    ];

    /**
     * Convert the persisted scope group model into a ScopeGroup DTO.
     */
    public function toGroup(): ScopeGroup
    {
        /** @var array<int, mixed> $rawScopes */
        $rawScopes = is_array($this->scopes) ? $this->scopes : [];

        $scopes = array_values(array_filter(
            $rawScopes,
            static fn (mixed $item): bool => is_string($item),
        ));

        return new ScopeGroup(
            name: $this->name,
            scopes: $scopes,
            description: $this->description ?? '',
        );
    }
}
