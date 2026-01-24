<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AuditLog extends Model
{
    use HasFactory, HasUuids;

    protected $primaryKey = 'id';
    protected $keyType = 'string';
    public $incrementing = false;

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function ($log) {
            if (empty($log->id)) {
                $log->id = Str::uuid();
            }
            if (empty($log->request_id)) {
                $log->request_id = Str::uuid();
            }
        });
    }

    protected $fillable = [
        'id',
        'occurred_at',
        'request_id',
        'actor_user_id',
        'actor_type',
        'event_type',
        'method',
        'path',
        'status_code',
        'ip',
        'user_agent',
        'target_type',
        'target_idcode',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /**
     * Scope: Filter by event type.
     */
    public function scopeOfEventType($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    /**
     * Scope: Filter by actor type.
     */
    public function scopeOfActorType($query, string $actorType)
    {
        return $query->where('actor_type', $actorType);
    }

    /**
     * Scope: Filter by status code.
     */
    public function scopeOfStatusCode($query, int $statusCode)
    {
        return $query->where('status_code', $statusCode);
    }

    /**
     * Scope: Recent events.
     */
    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('occurred_at', '>=', now()->subHours($hours));
    }
}
