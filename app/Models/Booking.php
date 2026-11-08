<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Booking extends Model
{
    protected $guarded = [];

    public const ACTIVE = ['EN_ATTENTE_PAIEMENT', 'CONFIRMEE'];

    protected function casts(): array
    {
        return ['cancelled_at' => 'datetime', 'reminder_24h_sent' => 'boolean', 'reminder_2h_sent' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(Slot::class);
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(InterviewNote::class)->latest();
    }
}
