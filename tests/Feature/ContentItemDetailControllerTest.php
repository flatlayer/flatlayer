<?php

namespace Tests\Feature;

use App\Models\ContentItem;
use App\Services\JinaSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentItemDetailControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        JinaSearchService::fake();
    }

    public function test_can_retrieve_content_item_by_type_and_slug()
    {
        $contentItem = ContentItem::factory()->create([
            'type' => 'post',
            'title' => 'Test Post',
            'slug' => 'test-post',
            'content' => 'This is a test post.',
        ]);

        $response = $this->getJson("/content/post/{$contentItem->slug}");

        $response->assertStatus(200)
            ->assertJson([
                'title' => 'Test Post',
                'slug' => 'test-post',
                'content' => 'This is a test post.',
                'type' => 'post',
            ]);
    }

    public function test_returns_404_for_non_existent_content_item()
    {
        $response = $this->getJson("/content/post/non-existent-slug");

        $response->assertStatus(404);
    }

    public function test_returns_404_for_mismatched_type_and_slug()
    {
        $contentItem = ContentItem::factory()->create([
            'type' => 'post',
            'title' => 'Test Post',
            'slug' => 'test-post',
            'content' => 'This is a test post.',
        ]);

        $response = $this->getJson("/content/document/{$contentItem->slug}");

        $response->assertStatus(404);
    }

    public function test_can_retrieve_different_content_types()
    {
        $post = ContentItem::factory()->create([
            'type' => 'post',
            'title' => 'Test Post',
            'slug' => 'test-post',
            'content' => 'This is a test post.',
        ]);

        $document = ContentItem::factory()->create([
            'type' => 'document',
            'title' => 'Test Document',
            'slug' => 'test-document',
            'content' => 'This is a test document.',
        ]);

        $postResponse = $this->getJson("/content/post/{$post->slug}");
        $documentResponse = $this->getJson("/content/document/{$document->slug}");

        $postResponse->assertStatus(200)
            ->assertJson([
                'title' => 'Test Post',
                'slug' => 'test-post',
                'content' => 'This is a test post.',
                'type' => 'post',
            ]);

        $documentResponse->assertStatus(200)
            ->assertJson([
                'title' => 'Test Document',
                'slug' => 'test-document',
                'content' => 'This is a test document.',
                'type' => 'document',
            ]);
    }
}
