<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** Abonnement à l'annuaire des lives : 30 jours et un nombre de lives (quota) pour un prix fixe. */
class LiveSubscription extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'reminder_sent_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (LiveSubscription $s) {
            do {
                $ref = 'ABO-'.Str::upper(Str::random(6));
            } while (self::where('reference', $ref)->exists());
            $s->reference ??= $ref;
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(LivePost::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'ACTIVE' && $this->ends_at?->isFuture();
    }

    /** Lives validés (seuls ceux-là comptent : un live refusé ou jamais traité ne coûte rien). */
    public function used(): int
    {
        return $this->posts()->whereNotNull('approved_at')->count();
    }

    public function remaining(): int
    {
        return max(0, $this->quota - $this->used());
    }
}
