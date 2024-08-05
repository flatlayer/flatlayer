<?php

namespace Tests\Fakes;

use App\Traits\Searchable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Tags\HasTags;
use Tests\Fakes\Factories\TestFilterModelFactory;

class TestFilterModel extends Model
{
    use HasFactory, HasTags, Searchable;

    protected $fillable = ['name', 'age', 'is_active', 'description', 'embedding'];

    public static $allowedFilters = ['name', 'age', 'is_active', 'description'];

    protected $casts = [
        'is_active' => 'boolean',
        'embedding' => 'array',
    ];

    public function toSearchableText(): string
    {
        return $this->name . ' ' . $this->description;
    }

    protected static function newFactory(): TestFilterModelFactory
    {
        return TestFilterModelFactory::new();
    }
}
