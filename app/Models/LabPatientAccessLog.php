<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LabPatientAccessLog extends Model
{
    protected $fillable = [
        'lab_patient_id',
        'lab_order_id',
        'actor',
        'action',
        'ip',
        'user_agent',
    ];
}
