<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable;

    public const ROLES = ['PARTICIPANT', 'INTERVIEWER', 'ADMIN'];

    protected $fillable = [
        'name', 'first_name', 'last_name', 'email', 'phone_e164', 'country', 'city',
        'tiktok_username', 'password',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'ADMIN';
    }

    public function isStaff(): bool
    {
        return in_array($this->role, ['ADMIN', 'INTERVIEWER'], true);
    }

    public static function normalizeTiktok(?string $v): ?string
    {
        if ($v === null) {
            return null;
        }
        $v = strtolower(preg_replace('/[\s@]+/', '', $v));

        return $v === '' ? null : $v;
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
