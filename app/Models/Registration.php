<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Registration extends Model
{
    protected $fillable = [
        'public_code_hash', 'public_code_encrypted', 'patient_name_encrypted', 'phone_encrypted',
        'visit_type', 'visit_date', 'appointment_time', 'complaint_encrypted', 'queue_number', 'status',
        'called_at', 'started_at', 'completed_at', 'no_show_at',
    ];

    protected function casts(): array
    {
        return ['visit_date' => 'date'];
    }
}