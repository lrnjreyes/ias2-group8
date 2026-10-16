<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'actor_id', 'event', 'subject_type', 'subject_id', 'ip_address', 'user_agent', 'metadata',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'created_at' => 'datetime'];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public static function record(Request $request, string $event, ?Model $subject = null, array $metadata = [], ?int $actorId = null): self
    {
        $resolvedActorId = $actorId ?? $request->user()?->id ?? ($subject instanceof User ? $subject->id : null);

        return self::create([
            'actor_id' => $resolvedActorId,
            'event' => $event,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
            'metadata' => $metadata ?: null,
        ]);
    }
}
