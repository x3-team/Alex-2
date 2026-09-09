<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * MERGE ONLY — do not overwrite the production BlogTag model.
 */
class BlogTag extends Model
{
    protected $fillable = ['name', 'slug'];
}
