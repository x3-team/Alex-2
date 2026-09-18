<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Blog;
use App\Models\Concerns\HasDoctorFlag;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;
    use HasDoctorFlag;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'avatar',
        'bio',
        'is_admin',
        'career_history',
        'seo_title',
        'seo_description',
        'seo_keywords',
        'education',
        'credentials',
        'profile_url',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'education' => 'array',
            'career_history' => 'array',
            'is_admin' => 'boolean',
        ];
    }
    public function blogs(): HasMany
    {
        return $this->hasMany(Blog::class);
    }
    public function authorCategories()
    {
        return $this->belongsToMany(
            \App\Models\AuthorCategory::class,  // Связываемая модель
            'author_author_category',           // Имя сводной таблицы
            'user_id',                          // 🔹 Ключ ТЕКУЩЕЙ модели (User) в сводной таблице
            'author_category_id'                // 🔹 Ключ СВЯЗАННОЙ модели (AuthorCategory) в сводной таблице
        )->withTimestamps();
    }

    protected $appends = ['author_categories'];

    public function getAuthorCategoriesAttribute()
    {
        return $this->authorCategories()->get();
    }

    public function isPublicAuthor(): bool
    {
        return \App\Support\PublicBlogAuthor::visible($this) === $this;
    }
}
