<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Project extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['needs' => 'array', 'links' => 'array', 'image_urls' => 'array'];
    }

    public const SECTORS = ['Agriculture', 'Tech', 'Commerce', 'Mode', 'Beauté', 'Éducation', 'Santé', 'Art/Culture', 'Services', 'Immigration/Mobilité', 'Autre'];

    public const STAGES = ['IDEE' => 'Idée', 'DEMARRAGE' => 'Démarrage', 'EN_ACTIVITE' => 'En activité', 'CROISSANCE' => 'En croissance'];

    public const NEEDS = ['Financement', 'Partenaires', 'Visibilité', 'Conseils', 'Autre'];

    public static function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'projet';
        $slug = $base;
        $i = 2;
        while (static::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function publications(): HasMany
    {
        return $this->hasMany(Publication::class);
    }

    public function scopePubliclyVisible($q)
    {
        return $q->whereHas('publications', fn ($p) => $p->where('status', 'PUBLIEE')->where('expires_at', '>', now()));
    }
}
