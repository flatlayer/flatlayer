<?php

namespace Tests\Fakes;

use App\Traits\HasMedia;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Tests\Fakes\Factories\FakePostFactory;

class FakePost extends Model
{
    use HasMedia, HasFactory, HasSlug;

    protected $table = 'posts';

    protected $fillable = [
        'title',
        'content',
        'slug',
    ];

    public static $allowedFilters = ['title'];

    protected static function newFactory()
    {
        return FakePostFactory::new();
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('title')
            ->saveSlugsTo('slug');
    }

    public function toSummaryArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
        ];
    }

    public static function search($query)
    {
        return static::where('title', 'like', "%{$query}%")
            ->orWhere('content', 'like', "%{$query}%");
    }
}
