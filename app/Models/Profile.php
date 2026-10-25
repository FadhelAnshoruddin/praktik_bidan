<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Profile extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id', 'name', 'title', 'motto', 'description', 'photo_url', 'phone', 'address',
        'maps_url', 'duration', 'capacity',
    ];
}