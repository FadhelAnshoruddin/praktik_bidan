<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpecialClosure extends Model
{
    protected $fillable = ['date', 'reason'];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }
}