<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LiveSession extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'accepted_modes' => 'array'];
    }

    public function slots(): HasMany
    {
        return $this->hasMany(Slot::class)->orderBy('position');
    }
}
