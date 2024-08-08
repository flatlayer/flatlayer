<?php

namespace Tests\Feature;

use App\Models\ContentItem;
use App\Services\JinaSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ListControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        JinaSearchService::fake();
    }

    public function test_index_returns_paginated_results()
    {
        ContentItem::factory()->count(20)->create(['type' => 'post']);

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
        ContentItem::factory()->count(20)->create(['type' => 'post']);

        $response = $this->getJson('/content/post?per_page=10');

        $response->assertStatus(200)
            ->assertJsonCount(10, 'data');
    }

    public function test_index_returns_error_for_invalid_type()
    {
        $response = $this->getJson('/content/invalid-type');

        $response->assertStatus(404);
    }

    public function test_index_applies_tag_filters()
    {
        $postA = ContentItem::factory()->create(['title' => 'Post A', 'type' => 'post']);
        $postB = ContentItem::factory()->create(['title' => 'Post B', 'type' => 'post']);
        $postC = ContentItem::factory()->create(['title' => 'Post C', 'type' => 'post']);

        $postA->attachTag('tag1');
        $postB->attachTag('tag2');
        $postC->attachTag('tag1');

        $filter = json_encode(['$tags' => ['tag1']]);
        $response = $this->getJson("/content/post?filter={$filter}");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.title', 'Post A')
            ->assertJsonPath('data.1.title', 'Post C');

        $filter = json_encode(['$tags' => ['tag2']]);
        $response = $this->getJson("/content/post?filter={$filter}");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Post B');

        $filter = json_encode(['$tags' => ['non_existent_tag']]);
        $response = $this->getJson("/content/post?filter={$filter}");

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    public function test_index_applies_field_filters()
    {
        ContentItem::factory()->create(['title' => 'Post A', 'type' => 'post']);
        ContentItem::factory()->create(['title' => 'Post B', 'type' => 'post']);

        $filter = json_encode(['title' => 'Post A']);
        $response = $this->getJson("/content/post?filter={$filter}");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Post A');
    }

    public function test_index_applies_operator_field_filters()
    {
        ContentItem::factory()->create(['title' => 'AAA Post', 'type' => 'post']);
        ContentItem::factory()->create(['title' => 'BBB Post', 'type' => 'post']);
        ContentItem::factory()->create(['title' => 'CCC Post', 'type' => 'post']);

        $filter = json_encode(['title' => ['$lt' => 'CCC Post']]);
        $response = $this->getJson("/content/post?filter={$filter}");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.title', 'BBB Post')
            ->assertJsonPath('data.1.title', 'AAA Post');
    }

    public function test_index_transforms_items_using_to_summary_array()
    {
        $post = ContentItem::factory()->create([
            'title' => 'Test Post',
            'content' => 'This content should not appear in summary',
            'slug' => 'test-post',
            'type' => 'post',
        ]);

        $response = $this->getJson('/content/post');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.id', $post->id)
            ->assertJsonPath('data.0.title', 'Test Post')
            ->assertJsonPath('data.0.slug', 'test-post')
            ->assertJsonMissing(['data.0.content']);
    }

    public function test_index_filters_by_type()
    {
        ContentItem::factory()->create(['title' => 'Post A', 'type' => 'post']);
        ContentItem::factory()->create(['title' => 'Document B', 'type' => 'document']);

        $response = $this->getJson('/content/post');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Post A');

        $response = $this->getJson('/content/document');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Document B');
    }
}
