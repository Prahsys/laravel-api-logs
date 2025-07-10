<?php

namespace Prahsys\ApiLogs\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class ApiLogItem extends Model
{
    use HasUuids, MassPrunable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'request_id',
        'path',
        'method',
        'api_version',
        'request_at',
        'response_at',
        'response_status',
        'is_error',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'request_at' => 'datetime:Y-m-d H:i:s.v',
        'response_at' => 'datetime:Y-m-d H:i:s.v',
        'response_status' => 'integer',
        'is_error' => 'boolean',
    ];

    /**
     * Get related models through the morph many relationship.
     */
    public function getRelatedModels(string $modelClass): MorphToMany
    {
        return $this->morphedByMany($modelClass, 'model', 'api_log_item_models');
    }

    /**
     * Get the duration in milliseconds.
     */
    public function getDurationMsAttribute(): ?int
    {
        if (! $this->request_at || ! $this->response_at) {
            return null;
        }

        return $this->response_at->diff($this->request_at)->milliseconds;
    }

    /**
     * Get the formatted duration.
     */
    public function getDurationFormatted(): ?string
    {
        $duration = $this->duration_ms;

        if ($duration === null) {
            return null;
        }

        if ($duration < 1000) {
            return $duration.'ms';
        }

        return round($duration / 1000, 2).'s';
    }

    /**
     * Get the prunable model query.
     */
    public function prunable()
    {
        $ttlHours = config('api-logs.database.pruning.ttl_hours', 24 * 365); // Default 365 days

        return static::where('created_at', '<=', now()->subHours($ttlHours));
    }
}
