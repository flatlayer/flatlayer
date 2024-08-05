<?php

namespace Tests\Feature;

use App\Services\ModelResolverService;
use Tests\Fakes\FakePost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SingleModelControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $modelResolver = $this->app->make(ModelResolverService::class);
        $modelResolver->addNamespace('Tests\\Fakes');
    }

    public function test_can_retrieve_model_by_slug()
    {
        $post = FakePost::factory()->create([
            'title' => 'Test Post',
            'slug' => 'test-post',
            'content' => 'This is a test post.',
        ]);

        $response = $this->getJson("/fake-posts/show/{$post->slug}");

        $response->assertStatus(200)
            ->assertJson([
                'title' => 'Test Post',
                'slug' => 'test-post',
                'content' => 'This is a test post.',
            ]);
    }

    public function test_returns_error_for_invalid_model_slug()
    {
        $response = $this->getJson("/invalid-models/show/some-slug");

        $response->assertStatus(400)
            ->assertJson([
                'error' => 'Invalid model',
            ]);
    }

    public function test_returns_404_for_non_existent_model()
    {
        $response = $this->getJson("/fake-posts/show/non-existent-slug");

        $response->assertStatus(404);
    }
}
