<?php

namespace Tests\Feature;

use App\Models\Entry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentDetailControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_can_retrieve_entry_by_type_and_slug()
    {
        $entry = Entry::factory()->create([
            'type' => 'post',
            'title' => 'Test Post',
            'slug' => 'test-post',
            'content' => 'This is a test post.',
        ]);

        $response = $this->getJson("/content/post/{$entry->slug}");

        $response->assertStatus(200)
            ->assertJson([
                'title' => 'Test Post',
                'slug' => 'test-post',
                'content' => [
                    "markdown" => "This is a test post.",
                    "html" => "<p>This is a test post.</p>\n"
                ],
                'type' => 'post',
            ]);
    }

    public function test_returns_404_for_non_existent_entry()
    {
        $response = $this->getJson('/content/post/non-existent-slug');

        $response->assertStatus(404);
    }

    public function test_returns_404_for_mismatched_type_and_slug()
    {
        $entry = Entry::factory()->create([
            'type' => 'post',
            'title' => 'Test Post',
            'slug' => 'test-post',
            'content' => 'This is a test post.',
        ]);

        // Attempt to retrieve a 'post' entry using 'document' type
        $response = $this->getJson("/content/document/{$entry->slug}");

        $response->assertStatus(404);
    }

    public function test_can_retrieve_different_entry_types()
    {
        $post = Entry::factory()->create([
            'type' => 'post',
            'title' => 'Test Post',
            'slug' => 'test-post',
            'content' => 'This is a test post.',
        ]);

        $document = Entry::factory()->create([
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
                'content' => [
                    "markdown" => "This is a test post.",
                    "html" => "<p>This is a test post.</p>\n"
                ],
                'type' => 'post',
            ]);

        $documentResponse->assertStatus(200)
            ->assertJson([
                'title' => 'Test Document',
                'slug' => 'test-document',
                'content' => [
                    "markdown" => "This is a test document.",
                    "html" => "<p>This is a test document.</p>\n"
                ],
                'type' => 'document',
            ]);
    }
}
