<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WeeklySchedule extends Model
{
    protected $primaryKey = 'day_of_week';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['day_of_week', 'is_open', 'open_time', 'close_time'];

    protected function casts(): array
    {
        return ['is_open' => 'boolean'];
    }
}