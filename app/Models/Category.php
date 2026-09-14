<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = ['name', 'slug', 'description'];

    public function blogs(): HasMany
    {
        return $this->hasMany(Blog::class);
    }
    public function authors()
    {
        return $this->belongsToMany(\App\Models\User::class, 'author_category', 'category_id', 'user_id')
            ->withTimestamps();
    }
}