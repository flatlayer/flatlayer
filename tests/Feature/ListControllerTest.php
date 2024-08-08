<?php

namespace Tests\Feature;

use App\Models\Entry;
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
        Entry::factory()->count(20)->create(['type' => 'post']);

        $response = $this->getJson('/entry/post');

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

        $response = $this->getJson('/entry/post?per_page=10');

        $response->assertStatus(200)
            ->assertJsonCount(10, 'data');
    }

    public function test_index_returns_error_for_invalid_type()
    {
        $response = $this->getJson('/entry/invalid-type');

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
        $response = $this->getJson("/entry/post?filter={$filter}");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.title', 'Post A')
            ->assertJsonPath('data.1.title', 'Post C');

        $filter = json_encode(['$tags' => ['tag2']]);
        $response = $this->getJson("/entry/post?filter={$filter}");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Post B');

        $filter = json_encode(['$tags' => ['non_existent_tag']]);
        $response = $this->getJson("/entry/post?filter={$filter}");

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    public function test_index_applies_field_filters()
    {
        Entry::factory()->create(['title' => 'Post A', 'type' => 'post']);
        Entry::factory()->create(['title' => 'Post B', 'type' => 'post']);

        $filter = json_encode(['title' => 'Post A']);
        $response = $this->getJson("/entry/post?filter={$filter}");

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
        $response = $this->getJson("/entry/post?filter={$filter}");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.title', 'BBB Post')
            ->assertJsonPath('data.1.title', 'AAA Post');
    }

    public function test_index_transforms_items_using_to_summary_array()
    {
        $post = Entry::factory()->create([
            'title' => 'Test Post',
            'content' => 'This content should not appear in summary',
            'slug' => 'test-post',
            'type' => 'post',
        ]);

        $response = $this->getJson('/entry/post');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.id', $post->id)
            ->assertJsonPath('data.0.title', 'Test Post')
            ->assertJsonPath('data.0.slug', 'test-post')
            ->assertJsonMissing(['data.0.content']);
    }

    public function test_index_filters_by_type()
    {
        Entry::factory()->create(['title' => 'Post A', 'type' => 'post']);
        Entry::factory()->create(['title' => 'Document B', 'type' => 'document']);

        $response = $this->getJson('/entry/post');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Post A');

        $response = $this->getJson('/entry/document');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Document B');
    }

    public function test_index_returns_only_specified_fields()
    {
        Entry::factory()->create([
            'title' => 'Test Post',
            'content' => 'This is the content',
            'excerpt' => 'This is the excerpt',
            'type' => 'post',
        ]);

        $fields = json_encode([
            'title',
        ]);

        $response = $this->getJson("/entry/post?fields={$fields}");

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => [['title']]])
            ->assertJsonMissing(['content'])
            ->assertJsonMissing(['excerpt'])
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

        $response = $this->getJson("/entry/post?fields={$fields}");

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

    public function test_index_returns_formatted_images()
    {
        $post = Entry::factory()->create(['type' => 'post']);
        $post->addAsset(base_path('tests/fixtures/test.png'), 'featured');

        $fields = json_encode(['title', 'images.featured']);

        $response = $this->getJson("/entry/post?fields={$fields}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'title',
                        'images' => [
                            'featured' => [
                                '*' => [
                                    'id',
                                    'url',
                                    'html',
                                    'meta' => [
                                        'width',
                                        'height',
                                        'aspect_ratio'
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ])
            ->assertJsonCount(1, 'data');

        $responseData = $response->json('data.0');
        $this->assertArrayHasKey('title', $responseData);
        $this->assertArrayHasKey('images', $responseData);
        $this->assertArrayHasKey('featured', $responseData['images']);

        $featuredImage = $responseData['images']['featured'][0];
        $this->assertIsInt($featuredImage['id']);
        $this->assertIsString($featuredImage['url']);
        $this->assertStringStartsWith('http://', $featuredImage['url']);
        $this->assertIsString($featuredImage['html']);
        $this->assertStringContainsString('<img', $featuredImage['html']);
        $this->assertIsArray($featuredImage['meta']);
        $this->assertArrayHasKey('width', $featuredImage['meta']);
        $this->assertArrayHasKey('height', $featuredImage['meta']);
        $this->assertArrayHasKey('aspect_ratio', $featuredImage['meta']);
        $this->assertIsInt($featuredImage['meta']['width']);
        $this->assertIsInt($featuredImage['meta']['height']);
        $this->assertIsNumeric($featuredImage['meta']['aspect_ratio']);
    }

    public function test_index_respects_field_options()
    {
        Entry::factory()->create([
            'title' => 'Test Post',
            'type' => 'post',
            'published_at' => now(),
            'meta' => ['views' => '1000'],
        ]);

        $fields = json_encode([
            ['published_at', 'date'],
            ['meta.views', 'integer'],
        ]);

        $response = $this->getJson("/entry/post?fields={$fields}");

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => [[
                'published_at',
                'meta' => ['views'],
            ]]])
            ->assertJsonCount(1, 'data');

        $responseData = $response->json('data.0');
        $this->assertIsString($responseData['published_at']);
        $this->assertIsInt($responseData['meta']['views']);
    }
}
