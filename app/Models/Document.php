<?php

namespace App\Models;

use App\Traits\HasMedia;
use App\Traits\MarkdownModel;
use App\Traits\Searchable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Pgvector\Laravel\Vector;
use WendellAdriel\Lift\Attributes\Cast;
use WendellAdriel\Lift\Attributes\Fillable;
use WendellAdriel\Lift\Attributes\PrimaryKey;
use WendellAdriel\Lift\Lift;

class Document extends Model
{
    use HasFactory, HasMedia, Searchable, MarkdownModel, Lift;

    #[PrimaryKey]
    public int $id;

    #[Fillable]
    public string $slug;

    #[Fillable]
    public ?string $title;

    #[Fillable]
    public ?string $content;

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function toSearchableText(): string
    {
        return '# ' . $this->title . "\n\n" . $this->content;
    }
}
