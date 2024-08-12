<?php

namespace Tests\Feature;

use App\Models\Entry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ListControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_index_returns_paginated_results()
    {
        Entry::factory()->count(20)->create(['type' => 'post']);

        $response = $this->getJson('/content/post');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'current_page',
                'per_page',
                'total',
            ])
            ->assertJsonCount(15, 'data'); // Default per_page is 15
    }

    public function test_index_respects_per_page_parameter()
    {
        Entry::factory()->count(20)->create(['type' => 'post']);

        $response = $this->getJson('/content/post?per_page=10');

        $response->assertStatus(200)
            ->assertJsonCount(10, 'data');
    }

    public function test_index_returns_404_for_invalid_type()
    {
        $response = $this->getJson('/content/invalid-type');

        $response->assertStatus(404);
    }

    public function test_index_applies_tag_filters()
    {
        $postA = Entry::factory()->create(['title' => 'Post A', 'type' => 'post']);
        $postB = Entry::factory()->create(['title' => 'Post B', 'type' => 'post']);
        $postC = Entry::factory()->create(['title' => 'Post C', 'type' => 'post']);

        $postA->attachTag('tag1');
        $postB->attachTag('tag2');
        $postC->attachTag('tag1');

        $filter = json_encode(['$tags' => ['tag1']]);
        $response = $this->getJson("/content/post?filter={$filter}");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['title' => 'Post A'])
            ->assertJsonFragment(['title' => 'Post C'])
            ->assertJsonMissing(['title' => 'Post B']);
    }

    public function test_index_applies_multiple_tag_filters()
    {
        $postA = Entry::factory()->create(['title' => 'Post A', 'type' => 'post']);
        $postB = Entry::factory()->create(['title' => 'Post B', 'type' => 'post']);
        $postC = Entry::factory()->create(['title' => 'Post C', 'type' => 'post']);

        $postA->attachTags(['tag1', 'tag2']);
        $postB->attachTag('tag2');
        $postC->attachTag('tag1');

        $filter = json_encode(['$tags' => ['tag1', 'tag2']]);
        $response = $this->getJson("/content/post?filter={$filter}");

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data')
            ->assertJsonFragment(['title' => 'Post A'])
            ->assertJsonFragment(['title' => 'Post B'])
            ->assertJsonFragment(['title' => 'Post C']);
    }

    public function test_index_applies_field_filters()
    {
        Entry::factory()->create(['title' => 'Post A', 'type' => 'post']);
        Entry::factory()->create(['title' => 'Post B', 'type' => 'post']);

        $filter = json_encode(['title' => 'Post A']);
        $response = $this->getJson("/content/post?filter={$filter}");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Post A');
    }

    public function test_index_applies_operator_field_filters()
    {
        Entry::factory()->create(['title' => 'AAA Post', 'type' => 'post']);
        Entry::factory()->create(['title' => 'BBB Post', 'type' => 'post']);
        Entry::factory()->create(['title' => 'CCC Post', 'type' => 'post']);

        $filter = json_encode(['title' => ['$lt' => 'CCC Post']]);
        $response = $this->getJson("/content/post?filter={$filter}");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.title', 'BBB Post')
            ->assertJsonPath('data.1.title', 'AAA Post');
    }

    public function test_index_applies_multiple_filters()
    {
        Entry::factory()->create(['title' => 'Post A', 'type' => 'post', 'published_at' => now()->subDays(5)]);
        Entry::factory()->create(['title' => 'Post B', 'type' => 'post', 'published_at' => now()->subDays(3)]);
        Entry::factory()->create(['title' => 'Post C', 'type' => 'post', 'published_at' => now()->subDay()]);

        $filter = json_encode([
            'published_at' => ['$gte' => now()->subDays(4)->toDateTimeString()],
            'title' => ['$ne' => 'Post C'],
        ]);
        $response = $this->getJson("/content/post?filter={$filter}");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Post B');
    }

    public function test_index_applies_search_filter()
    {
        Entry::factory()->create(['title' => 'Laravel Tutorial', 'content' => 'Learn Laravel', 'type' => 'post']);
        Entry::factory()->create(['title' => 'PHP Basics', 'content' => 'Introduction to PHP', 'type' => 'post']);

        $response = $this->getJson('/content/post?search=Laravel');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.title', 'Laravel Tutorial');
    }

    public function test_index_returns_only_specified_fields()
    {
        Entry::factory()->create([
            'title' => 'Test Post',
            'content' => 'This is the content',
            'excerpt' => 'This is the excerpt',
            'type' => 'post',
        ]);

        $fields = json_encode(['title', 'excerpt']);

        $response = $this->getJson("/content/post?fields={$fields}");

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => [['title', 'excerpt']]])
            ->assertJsonMissing(['content'])
            ->assertJsonCount(1, 'data');
    }

    public function test_index_returns_nested_meta_fields()
    {
        Entry::factory()->create([
            'title' => 'Test Post',
            'type' => 'post',
            'meta' => [
                'author' => 'John Doe',
                'seo' => [
                    'description' => 'SEO description',
                    'keywords' => ['key1', 'key2'],
                ],
            ],
        ]);

        $fields = json_encode(['title', 'meta.author', 'meta.seo.description']);

        $response = $this->getJson("/content/post?fields={$fields}");

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => [[
                'title',
                'meta' => [
                    'author',
                    'seo' => ['description'],
                ],
            ]]])
            ->assertJsonMissing(['meta' => ['seo' => ['keywords']]])
            ->assertJsonCount(1, 'data');
    }

    public function test_index_respects_order_parameter()
    {
        Entry::factory()->create(['title' => 'Post A', 'type' => 'post']);
        Entry::factory()->create(['title' => 'Post B', 'type' => 'post']);
        Entry::factory()->create(['title' => 'Post C', 'type' => 'post']);

        $order = json_encode(['title' => 'desc']);
        $response = $this->getJson("/content/post?order={$order}");

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.title', 'Post C')
            ->assertJsonPath('data.1.title', 'Post B')
            ->assertJsonPath('data.2.title', 'Post A');
    }

    public function test_index_handles_invalid_json_filters()
    {
        $invalidFilter = 'invalid-json';
        $response = $this->getJson("/content/post?filter={$invalidFilter}");

        $response->assertStatus(400)
            ->assertJson(['error' => 'The filter field must be a valid JSON string.']);
    }

    public function test_index_handles_empty_result_set()
    {
        $filter = json_encode(['title' => 'Non-existent Post']);
        $response = $this->getJson("/content/post?filter={$filter}");

        $response->assertStatus(404);
    }
}
