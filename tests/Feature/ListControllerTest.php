<?php

namespace Tests\Feature;

use App\Http\Controllers\ContentItemListController;
use App\Services\ModelResolverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Tests\Fakes\FakePost;
use Mockery;

class ListControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $modelResolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->modelResolver = Mockery::mock(ModelResolverService::class);
        $this->app->instance(ModelResolverService::class, $this->modelResolver);

        $this->modelResolver->shouldReceive('resolve')
            ->with('fake-posts')
            ->andReturn(FakePost::class);
    }

    public function test_index_returns_paginated_results()
    {
        FakePost::factory()->count(20)->create();

        $response = $this->getJson('/fake-posts/list');

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
        FakePost::factory()->count(20)->create();

        $response = $this->getJson('/fake-posts/list?per_page=10');

        $response->assertStatus(200)
            ->assertJsonCount(10, 'data');
    }

    public function test_index_returns_error_for_invalid_model()
    {
        $this->modelResolver->shouldReceive('resolve')
            ->with('invalid-model')
            ->andReturnNull();

        $response = $this->getJson('/invalid-model/list');

        $response->assertStatus(400)
            ->assertJson(['error' => 'Invalid model']);
    }

    public function test_index_applies_tag_filters()
    {
        $postA = FakePost::factory()->create(['title' => 'Post A']);
        $postB = FakePost::factory()->create(['title' => 'Post B']);
        $postC = FakePost::factory()->create(['title' => 'Post C']);

        $postA->attachTag('tag1');
        $postB->attachTag('tag2');
        $postC->attachTag('tag1');

        $filter = json_encode(['$tags' => ['tag1']]);
        $response = $this->getJson("/fake-posts/list?filter={$filter}");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.title', 'Post A')
            ->assertJsonPath('data.1.title', 'Post C');

        $filter = json_encode(['$tags' => ['tag2']]);
        $response = $this->getJson("/fake-posts/list?filter={$filter}");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Post B');

        $filter = json_encode(['$tags' => ['non_existent_tag']]);
        $response = $this->getJson("/fake-posts/list?filter={$filter}");

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    public function test_index_applies_field_filters()
    {
        FakePost::factory()->create(['title' => 'Post A']);
        FakePost::factory()->create(['title' => 'Post B']);

        $filter = json_encode(['title' => 'Post A']);
        $response = $this->getJson("/fake-posts/list?filter={$filter}");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Post A');
    }

    public function test_index_applies_operator_field_filters()
    {
        FakePost::factory()->create(['title' => 'AAA Post']);
        FakePost::factory()->create(['title' => 'BBB Post']);
        FakePost::factory()->create(['title' => 'CCC Post']);

        $filter = json_encode(['title' => ['$lt' => 'CCC Post']]);
        $response = $this->getJson("/fake-posts/list?filter={$filter}");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.title', 'AAA Post')
            ->assertJsonPath('data.1.title', 'BBB Post');
    }

    public function test_index_transforms_items_using_to_summary_array()
    {
        $post = FakePost::factory()->create([
            'title' => 'Test Post',
            'content' => 'This content should not appear in summary',
            'slug' => 'test-post',
        ]);

        $response = $this->getJson('/fake-posts/list');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.id', $post->id)
            ->assertJsonPath('data.0.title', 'Test Post')
            ->assertJsonPath('data.0.slug', 'test-post')
            ->assertJsonMissing(['data.0.content']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
