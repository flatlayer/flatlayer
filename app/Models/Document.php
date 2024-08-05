<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Searchable;
use Spatie\Tags\HasTags;

class Document extends Model
{
    use HasFactory, HasTags, Searchable;

    protected $fillable = [
        'title',
        'content',
        'slug',
    ];

    public function getRouteKeyName()
    {
        return 'slug';
    }

    public function toSearchableText(): string
    {
        return $this->title . "\n\n" . $this->content;
    }
}
