<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DoctorMaterial extends Model
{
    protected $fillable = [
        'title',
        'file_path',
        'category',
        'date',
    ];
}
