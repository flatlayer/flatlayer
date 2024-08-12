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

        $response = $this->getJson("/entry/post/{$entry->slug}");

        $response->assertStatus(200)
            ->assertJson([
                'title' => 'Test Post',
                'slug' => 'test-post',
                'content' => "This is a test post.",
                'type' => 'post',
            ]);
    }

    public function test_returns_404_for_non_existent_entry()
    {
        $response = $this->getJson('/entry/post/non-existent-slug');

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
        $response = $this->getJson("/entry/document/{$entry->slug}");

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

        $postResponse = $this->getJson("/entry/post/{$post->slug}");
        $documentResponse = $this->getJson("/entry/document/{$document->slug}");

        $postResponse->assertStatus(200)
            ->assertJson([
                'title' => 'Test Post',
                'slug' => 'test-post',
                'content' => "This is a test post.",
                'type' => 'post',
            ]);

        $documentResponse->assertStatus(200)
            ->assertJson([
                'title' => 'Test Document',
                'slug' => 'test-document',
                'content' => "This is a test document.",
                'type' => 'document',
            ]);
    }
}
