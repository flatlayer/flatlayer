<?php

namespace Tests\Fakes;

use App\Traits\HasMedia;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Tests\Fakes\Factories\FakePostFactory;

class FakePost extends Model
{
    use HasMedia, HasFactory;

    protected $table = 'posts';

    protected $fillable = [
        'title',
        'content',
    ];

    protected static function newFactory()
    {
        return FakePostFactory::new();
    }
}
