<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $fillable = ['name', 'email', 'password', 'chave_maldicoes'];

    protected $hidden = ['password', 'remember_token'];

    // A senha é criptografada no AuthController (Hash::make), então não há
    // cast "hashed" aqui para não criptografar duas vezes.

    public function personagens(): HasMany
    {
        return $this->hasMany(Personagem::class);
    }

    public function mesas(): HasMany
    {
        return $this->hasMany(Mesa::class);
    }
}