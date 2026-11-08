<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Package extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['allowed_modes' => 'array', 'includes_publication' => 'boolean', 'is_active' => 'boolean'];
    }

    public function priceFor(?string $mode): int
    {
        return (int) match ($mode) {
            'SUR_PLACE' => $this->price_on_site ?? $this->price,
            'A_DISTANCE' => $this->price_remote ?? $this->price,
            default => $this->price,
        };
    }

    public function allows(string $mode): bool
    {
        return in_array($mode, $this->allowed_modes ?? [], true);
    }
}
