<?php

namespace Tests\Feature;

use App\Http\Controllers\ListController;
use App\Services\ModelResolverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Tests\Fakes\FakePost;
use Mockery;
use Spatie\Tags\Tag;

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

    public function test_index_applies_search_query()
    {
        FakePost::factory()->count(5)->create();
        $searchPost = FakePost::factory()->create(['title' => 'Searchable Post']);

        // Assuming the search is performed on the 'title' field
        $response = $this->getJson('/fake-posts/list?query=Searchable');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Searchable Post');
    }

    public function test_index_applies_tag_filters()
    {
        // Skip this test if the FakePost model doesn't support tags
        $this->markTestSkipped('FakePost model does not support tags yet.');

        $post1 = FakePost::factory()->create();
        $post2 = FakePost::factory()->create();

        $tag = Tag::create(['name' => 'test-tag', 'type' => 'test-type']);
        $post1->tags()->attach($tag);

        $response = $this->getJson('/fake-posts/list?filters[tags][test-type]=test-tag');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $post1->id);
    }

    public function test_index_applies_field_filters()
    {
        FakePost::factory()->create(['title' => 'Post A']);
        FakePost::factory()->create(['title' => 'Post B']);

        $response = $this->getJson('/fake-posts/list?filters[title]=Post A');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Post A');
    }

    public function test_index_applies_operator_field_filters()
    {
        FakePost::factory()->create(['title' => 'AAA Post']);
        FakePost::factory()->create(['title' => 'BBB Post']);
        FakePost::factory()->create(['title' => 'CCC Post']);

        $response = $this->getJson('/fake-posts/list?filters[title][operator]=<&filters[title][value]=CCC Post');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.title', 'AAA Post')
            ->assertJsonPath('data.1.title', 'BBB Post');
    }

    public function test_index_ignores_non_filterable_fields()
    {
        FakePost::factory()->create(['content' => 'Filtered content']);
        FakePost::factory()->create(['content' => 'Other content']);

        $response = $this->getJson('/fake-posts/list?filters[content]=Filtered content');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
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
