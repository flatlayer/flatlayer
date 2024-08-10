<?php

namespace Tests\Unit\Query\Filter;

use App\Models\Entry;
use App\Query\EntryFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CombinedFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_combined_filters()
    {
        $olderJohn = Entry::factory()->create([
            'title' => 'John Smith',
            'type' => 'post',
            'content' => 'An older John post',
            'meta' => ['age' => 50],
            'published_at' => now()->subDays(30),
        ]);
        $olderJohn->attachTag('important');

        $youngerJohn = Entry::factory()->create([
            'title' => 'John Smith',
            'type' => 'post',
            'content' => 'A younger John post',
            'meta' => ['age' => 30],
            'published_at' => now()->subDays(10),
        ]);
        $youngerJohn->attachTag('important');

        $filters = [
            'meta.age' => ['$gte' => 25, '$lt' => 40],
            'published_at' => ['$gte' => now()->subDays(20)->toDateTimeString()],
            '$tags' => ['important'],
            '$search' => 'John',
        ];

        $filtered = (new EntryFilter(Entry::query(), $filters))->apply();

        $this->assertCount(1, $filtered);

        $model = $filtered->first();
        $this->assertEquals('John Smith', $model->title);
        $this->assertEquals(30, $model->meta['age']);
        $this->assertTrue($model->published_at->gt(now()->subDays(20)));
        $this->assertTrue($model->tags->pluck('name')->contains('important'));
        $this->assertStringContainsString('younger John', $model->content);
    }
}
