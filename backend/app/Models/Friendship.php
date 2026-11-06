<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Friendship extends Model
{
    public const PENDING = 'PENDING';
    public const ACCEPTED = 'ACCEPTED';

    protected $guarded = [];

    protected function casts(): array { return ['accepted_at' => 'datetime']; }

    public function requester(): BelongsTo { return $this->belongsTo(User::class, 'requester_id'); }
    public function addressee(): BelongsTo { return $this->belongsTo(User::class, 'addressee_id'); }

    /** O vínculo entre duas pessoas, em qualquer direção. */
    public function scopeBetween(Builder $query, User $a, User $b): void
    {
        $query->where(fn ($q) => $q->where('requester_id', $a->id)->where('addressee_id', $b->id))
            ->orWhere(fn ($q) => $q->where('requester_id', $b->id)->where('addressee_id', $a->id));
    }

    public function scopeInvolving(Builder $query, User $user): void
    {
        $query->where(fn ($q) => $q->where('requester_id', $user->id)->orWhere('addressee_id', $user->id));
    }

    public function otherThan(User $user): User
    {
        return $this->requester_id === $user->id ? $this->addressee : $this->requester;
    }
}
