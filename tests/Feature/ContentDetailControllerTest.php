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
        $this->setupHierarchicalContent();
    }

    protected function setupHierarchicalContent(): void
    {
        // Create root level index
        Entry::factory()->create([
            'type' => 'doc',
            'title' => 'Documentation Root',
            'content' => 'Root documentation content',
            'slug' => 'docs/index',
            'is_index' => true,
        ]);

        // Create nested documentation structure
        Entry::factory()->create([
            'type' => 'doc',
            'title' => 'Getting Started',
            'content' => 'Getting started guide',
            'slug' => 'docs/getting-started/index',
            'is_index' => true,
        ]);

        Entry::factory()->create([
            'type' => 'doc',
            'title' => 'Installation Guide',
            'content' => 'Installation instructions',
            'slug' => 'docs/getting-started/installation',
            'is_index' => false,
        ]);

        // Create a section with both index and content file
        Entry::factory()->create([
            'type' => 'doc',
            'title' => 'Tutorials Section',
            'content' => 'Tutorial index content',
            'slug' => 'docs/tutorials/index',
            'is_index' => true,
        ]);

        Entry::factory()->create([
            'type' => 'doc',
            'title' => 'Basic Tutorials',
            'content' => 'Basic tutorial content',
            'slug' => 'docs/tutorials/basics',
            'is_index' => false,
        ]);

        // Create deeply nested content
        Entry::factory()->create([
            'type' => 'doc',
            'title' => 'Cloud Deployment',
            'content' => 'Cloud deployment tutorial',
            'slug' => 'docs/tutorials/advanced/deployment/cloud',
            'is_index' => false,
        ]);
    }

    public function test_can_retrieve_root_level_entry()
    {
        $entry = Entry::factory()->create([
            'type' => 'post',
            'title' => 'Test Post',
            'slug' => 'test-post',
            'content' => 'This is a test post.',
            'is_index' => false,
        ]);

        $response = $this->getJson("/entry/post/{$entry->slug}");

        $response->assertStatus(200)
            ->assertJsonFragment([
                'title' => 'Test Post',
                'slug' => 'test-post',
                'content' => 'This is a test post.',
                'type' => 'post',
            ]);
    }

    public function test_can_retrieve_nested_entry()
    {
        $response = $this->getJson('/entry/doc/docs/getting-started/installation');

        $response->assertStatus(200)
            ->assertJsonFragment([
                'title' => 'Installation Guide',
                'slug' => 'docs/getting-started/installation',
                'type' => 'doc',
            ]);
    }

    public function test_redirects_to_index_file_when_accessing_directory()
    {
        $response = $this->getJson('/entry/doc/docs/getting-started');

        $response->assertStatus(307)
            ->assertJson([
                'redirect' => true,
                'location' => '/entry/doc/docs/getting-started/index',
            ]);
    }

    public function test_redirects_to_parent_index_for_nonexistent_nested_path()
    {
        $response = $this->getJson('/entry/doc/docs/getting-started/nonexistent');

        $response->assertStatus(307)
            ->assertJson([
                'redirect' => true,
                'location' => '/entry/doc/docs/getting-started/index',
            ]);
    }

    public function test_returns_404_for_non_existent_entry_and_no_index()
    {
        $response = $this->getJson('/entry/doc/docs/nonexistent/path');
        $response->assertStatus(404);
    }

    public function test_returns_404_for_mismatched_type_and_slug()
    {
        $response = $this->getJson('/entry/post/docs/getting-started/installation');
        $response->assertStatus(404);
    }

    public function test_can_retrieve_deeply_nested_entry()
    {
        $response = $this->getJson('/entry/doc/docs/tutorials/advanced/deployment/cloud');

        $response->assertStatus(200)
            ->assertJsonFragment([
                'title' => 'Cloud Deployment',
                'slug' => 'docs/tutorials/advanced/deployment/cloud',
                'type' => 'doc',
            ]);
    }

    public function test_handles_mixed_content_and_index_files()
    {
        $response = $this->getJson('/entry/doc/docs/tutorials/index');
        $response->assertStatus(200)
            ->assertJsonFragment([
                'title' => 'Tutorials Section',
                'is_index' => true,
            ]);

        $response = $this->getJson('/entry/doc/docs/tutorials/basics');
        $response->assertStatus(200)
            ->assertJsonFragment([
                'title' => 'Basic Tutorials',
                'is_index' => false,
            ]);
    }

    public function test_returns_navigation_data_when_requested()
    {
        $response = $this->getJson('/entry/doc/docs/tutorials/basics?includes=navigation');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'title',
                'content',
                'navigation' => [
                    'ancestors',
                    'siblings',
                    'children',
                ],
            ]);

        $responseData = $response->json();
        $this->assertContains('Tutorials Section', array_column($responseData['navigation']['ancestors'], 'title'));
    }

    public function test_index_file_includes_parent_navigation()
    {
        $response = $this->getJson('/entry/doc/docs/tutorials/index?includes=navigation');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'navigation' => [
                    'parent',
                    'ancestors',
                    'siblings',
                    'children',
                ],
            ]);

        $responseData = $response->json();
        $this->assertEquals('Documentation Root', $responseData['navigation']['parent']['title']);
    }

    public function test_handles_trailing_slashes_consistently()
    {
        $response = $this->getJson('/entry/doc/docs/getting-started/');
        $response->assertStatus(307)
            ->assertJson([
                'redirect' => true,
                'location' => '/entry/doc/docs/getting-started/index',
            ]);

        $response = $this->getJson('/entry/doc/docs/getting-started');
        $response->assertStatus(307)
            ->assertJson([
                'redirect' => true,
                'location' => '/entry/doc/docs/getting-started/index',
            ]);
    }

    public function test_preserves_query_parameters_during_redirect()
    {
        $response = $this->getJson('/entry/doc/docs/getting-started?includes=navigation');

        $response->assertStatus(307)
            ->assertJson([
                'redirect' => true,
                'location' => '/entry/doc/docs/getting-started/index',
            ]);
    }
}
