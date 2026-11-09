<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/** Comptes de l'équipe OCD uniquement (administrateur, animateur). Les vendeurs n'ont pas de compte. */
class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const ROLES = ['INTERVIEWER', 'ADMIN', 'VENDOR'];

    protected $fillable = ['name', 'first_name', 'last_name', 'email', 'phone_e164', 'country', 'city', 'password'];

    /** Staff = équipe OCD ; les comptes VENDOR (vendeurs) n'ont accès qu'à leur espace. */
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

    public function shops(): HasMany
    {
        return $this->hasMany(Shop::class);
    }

    public function isVendor(): bool
    {
        return $this->role === 'VENDOR';
    }
}
