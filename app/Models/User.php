<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Hash;

class User extends Authenticatable
{
    use HasFactory;

    protected $fillable = ['name', 'password', 'is_admin'];

    public function isAdmin(): bool
    {
        return (bool) $this->is_admin;
    }
}
