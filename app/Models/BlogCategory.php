<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * MERGE ONLY — do not overwrite the production BlogCategory model.
 */
class BlogCategory extends Model
{
    protected $fillable = ['name', 'slug'];
}
