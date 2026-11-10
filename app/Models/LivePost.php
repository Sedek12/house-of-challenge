<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un live TikTok envoyé par un vendeur : en attente de vérification, puis affiché pendant une durée limitée. */
class LivePost extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['approved_at' => 'datetime', 'live_until' => 'datetime', 'ended_at' => 'datetime', 'extended' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(LiveSubscription::class, 'live_subscription_id');
    }

    /** Lives visibles sur le site en ce moment (indépendant du passage de la tâche planifiée). */
    public function scopeOnAir(Builder $q): Builder
    {
        return $q->where('status', 'EN_LIGNE')->where('live_until', '>', now());
    }

    public function isOnAir(): bool
    {
        return $this->status === 'EN_LIGNE' && $this->live_until?->isFuture();
    }
}
