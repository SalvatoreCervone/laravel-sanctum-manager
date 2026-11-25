<?php

declare(strict_types=1);

namespace SanctumManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $personal_access_token_id
 * @property string|null $ip_address
 * @property string $method
 * @property string $path
 * @property int|null $status_code
 * @property string|null $user_agent
 * @property Carbon|null $created_at
 */
class PersonalAccessTokenLog extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'sanctum_token_logs';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'personal_access_token_id',
        'ip_address',
        'method',
        'path',
        'status_code',
        'user_agent',
        'created_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'created_at' => 'datetime',
        'status_code' => 'integer',
    ];

    /**
     * Get the personal access token that owns this audit log entry.
     *
     * @return BelongsTo<PersonalAccessToken, $this>
     */
    public function token(): BelongsTo
    {
        return $this->belongsTo(PersonalAccessToken::class, 'personal_access_token_id');
    }
}
