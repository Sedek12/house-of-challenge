<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ShopProduct extends Model
{
    protected $guarded = [];

    protected $appends = ['photo_url'];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photo_path ? Storage::disk('public')->url($this->photo_path) : null;
    }
}
