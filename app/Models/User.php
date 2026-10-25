<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $fillable = ['email', 'password'];

    protected $hidden = ['password', 'remember_token'];

    public function getAuthPassword(): string
    {
        $password = parent::getAuthPassword();

        return str_starts_with($password, '$2b$')
            ? '$2y$'.substr($password, 4)
            : $password;
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}