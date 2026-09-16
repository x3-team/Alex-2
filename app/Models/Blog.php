<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Concerns\HasBlogAudience;

class Blog extends Model
{
    use HasFactory, SoftDeletes;
    use HasBlogAudience;

    protected $fillable = [
        'user_id',
        'title',
        'slug',
        'excerpt',
        'table_of_contents',
        'sources',
        'faqs',
        'content',
        'preview_image',
        'duration',
        'category_id',
        'sort_order',
        'published_at',
        'cta_title',
        'cta_description',
        'cta_button_text',
        'cta_button_url',
        'seo_title',
        'seo_description',
        'seo_keywords',
        'is_active',
        'canonical_url',
        'noindex',
        'og_title',
        'og_description',
        'audience',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'noindex' => 'boolean',
        'faqs' => 'array',
    ];

    /**
     * Автоматическая установка и обновление даты публикации
     */
    protected static function booted(): void
    {
        static::saving(function (Blog $blog) {
            if ($blog->is_active && empty($blog->published_at)) {
                $blog->published_at = now();
            }
        });
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function publicAuthor(): ?User
    {
        $author = \App\Support\PublicBlogAuthor::visible($this->author);

        return $author instanceof User ? $author : null;
    }

    public function hideNonPublicAuthor(): static
    {
        if ($this->relationLoaded('author')) {
            $this->setRelation('author', $this->publicAuthor());
        }

        if ($this->relationLoaded('relatedPosts')) {
            $this->relatedPosts->each->hideNonPublicAuthor();
        }

        return $this;
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function relatedPosts(): BelongsToMany
    {
        return $this->belongsToMany(
            Blog::class,
            'blog_related',
            'blog_id',
            'related_blog_id'
        )->withTimestamps();
    }

    public static function slugifyTitle(string $title): string
    {
        $map = [
            'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'e','ж'=>'z','з'=>'z',
            'и'=>'i','й'=>'i','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r',
            'с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'x','ц'=>'c','ч'=>'c','ш'=>'s','щ'=>'shh',
            'ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'iu','я'=>'ia',
        ];
        $title = mb_strtolower($title, 'UTF-8');
        $out = '';
        $len = mb_strlen($title, 'UTF-8');
        for ($i = 0; $i < $len; $i++) {
            $ch = mb_substr($title, $i, 1, 'UTF-8');
            $out .= array_key_exists($ch, $map) ? $map[$ch] : $ch;
        }
        $out = preg_replace('/[^a-z0-9\s-]/', '', $out);
        $out = preg_replace('/[\s_-]+/', '-', $out);
        return trim((string) $out, '-');
    }

    public function setSlugAttribute($value)
    {
        // Не трогаем уже заданный ЧПУ (в т.ч. published nested slugs). Пустой — та же карта, что JS RU_TO_LAT.
        if (is_string($value) && trim($value) !== '') {
            $this->attributes['slug'] = $value;
            return;
        }
        $this->attributes['slug'] = static::slugifyTitle((string) $this->title);
    }

    public function tags()
    {
        return $this->belongsToMany(BlogTag::class, 'blog_tag', 'blog_id', 'blog_tag_id')
            ->withTimestamps();
    }

    public function ratings()
    {
        return $this->hasMany(\App\Models\BlogRating::class);
    }

    public function averageRating(): float
    {
        return round($this->ratings()->avg('rating') ?? 0, 1);
    }

    public function ratingsCount(): int
    {
        return $this->ratings()->count();
    }
}