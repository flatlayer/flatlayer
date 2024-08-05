<?php

namespace Tests\Fakes;

use App\Traits\Searchable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FakeSearchableModel extends Model
{
    use HasFactory, Searchable;

    protected $fillable = ['title', 'content', 'embedding'];

    protected $casts = [
        'embedding' => 'array',
    ];

    public function toSearchableText(): string
    {
        return $this->title . ' ' . $this->content;
    }

    public static function defaultSearchableQuery()
    {
        return static::query();
    }
}
