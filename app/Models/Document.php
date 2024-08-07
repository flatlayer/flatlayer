<?php

namespace App\Models;

use App\Markdown\CustomMarkdownRenderer;
use App\Traits\HasMediaFiles;
use App\Traits\MarkdownContentModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Searchable;
use Pgvector\Laravel\Vector;

class Document extends Model
{
    use HasFactory, HasMediaFiles, Searchable, MarkdownContentModel;

    protected $fillable = [
        'title',
        'content',
        'excerpt',
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

    public function toSummaryArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
        ];
    }

    public function toDetailArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'content' => $this->getParsedContent(),
        ];
    }
}
