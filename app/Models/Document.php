<?php

namespace App\Models;

use App\Traits\HasMedia;
use App\Traits\MarkdownModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Searchable;
use Pgvector\Laravel\Vector;

class Document extends Model
{
    use HasFactory, HasMedia, Searchable, MarkdownModel;

    protected $fillable = [
        'title',
        'content',
        'slug',
    ];

    protected $casts = [
        'embedding' => Vector::class
    ];

    public function getRouteKeyName()
    {
        return 'slug';
    }

    public function toSearchableText(): string
    {
        return '# ' . $this->title . "\n\n" . $this->content;
    }
}
