<?php

namespace Tests\Fakes;

use App\Traits\HasMedia;
use App\Traits\MarkdownModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Tests\Fakes\Factories\FakePostFactory;
use Spatie\Tags\HasTags;

class FakePost extends Model
{
    use HasMedia, HasFactory, HasTags, MarkdownModel;

    protected $table = 'posts';

    protected $fillable = [
        'title',
        'content',
        'slug',
    ];

    public static $allowedFilters = ['title', 'tags'];

    protected static function newFactory()
    {
        return FakePostFactory::new();
    }

    public function toSummaryArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'tags' => $this->tags->pluck('name')->toArray(),
        ];
    }

    public static function search($query)
    {
        return static::where('title', 'like', "%{$query}%")
            ->orWhere('content', 'like', "%{$query}%");
    }
}
