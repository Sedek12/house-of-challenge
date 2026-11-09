<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Package extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['includes' => 'array', 'is_active' => 'boolean'];
    }
}
