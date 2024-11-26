<?php

namespace Tests\Feature\Controllers;

use App\Models\Entry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BatchShowControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupHierarchicalContent();
    }

    protected function setupHierarchicalContent(): void
    {
        // Create root level entries
        Entry::factory()->create([
            'type' => 'doc',
            'title' => 'Documentation Root',
            'content' => 'Root documentation content',
            'slug' => 'docs',
        ]);

        // Create nested documentation structure
        Entry::factory()->create([
            'type' => 'doc',
            'title' => 'Getting Started',
            'content' => 'Getting started guide',
            'slug' => 'docs/getting-started',
        ]);

        Entry::factory()->create([
            'type' => 'doc',
            'title' => 'Installation Guide',
            'content' => 'Installation instructions',
            'slug' => 'docs/getting-started/installation',
        ]);

        Entry::factory()->create([
            'type' => 'doc',
            'title' => 'Configuration Guide',
            'content' => 'Configuration instructions',
            'slug' => 'docs/getting-started/configuration',
        ]);

        // Create another section with content files
        Entry::factory()->create([
            'type' => 'doc',
            'title' => 'Tutorials Section',
            'content' => 'Tutorial section content',
            'slug' => 'docs/tutorials',
        ]);

        Entry::factory()->create([
            'type' => 'doc',
            'title' => 'Basic Tutorial',
            'content' => 'Basic tutorial content',
            'slug' => 'docs/tutorials/basic',
        ]);

        Entry::factory()->create([
            'type' => 'doc',
            'title' => 'Advanced Tutorial',
            'content' => 'Advanced tutorial content',
            'slug' => 'docs/tutorials/advanced',
        ]);

        // Create deeply nested content
        Entry::factory()->create([
            'type' => 'doc',
            'title' => 'Cloud Deployment',
            'content' => 'Cloud deployment tutorial',
            'slug' => 'docs/tutorials/deployment/cloud',
        ]);
    }

    public function test_can_retrieve_multiple_entries_by_paths()
    {
        $paths = 'docs/getting-started/installation,docs/getting-started/configuration';
        $response = $this->getJson("/entries/doc/batch?slugs={$paths}");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.title', 'Installation Guide')
            ->assertJsonPath('data.1.title', 'Configuration Guide');
    }

    public function test_batch_retrieval_with_nested_paths()
    {
        $paths = 'docs/tutorials,docs/tutorials/deployment/cloud';
        $response = $this->getJson("/entries/doc/batch?slugs={$paths}");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.title', 'Tutorials Section')
            ->assertJsonPath('data.1.title', 'Cloud Deployment');
    }

    public function test_batch_show_respects_fields_parameter()
    {
        $paths = 'docs/getting-started/installation,docs/getting-started/configuration';
        $fields = json_encode(['title', 'slug']);

        $response = $this->getJson("/entries/doc/batch?slugs={$paths}&fields={$fields}");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [
                    ['title', 'slug'],
                    ['title', 'slug'],
                ],
            ])
            ->assertJsonMissing(['content']);
    }

    public function test_returns_404_for_non_existent_paths()
    {
        $paths = 'docs/getting-started/installation,docs/nonexistent/path';
        $response = $this->getJson("/entries/doc/batch?slugs={$paths}");

        $response->assertStatus(404)
            ->assertJson(['error' => 'No items found for the specified type and slugs']);
    }

    public function test_maintains_request_order()
    {
        $paths = 'docs/tutorials/advanced,docs/getting-started/installation,docs/tutorials/basic';
        $response = $this->getJson("/entries/doc/batch?slugs={$paths}");

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.title', 'Advanced Tutorial')
            ->assertJsonPath('data.1.title', 'Installation Guide')
            ->assertJsonPath('data.2.title', 'Basic Tutorial');
    }

    public function test_handles_duplicate_paths()
    {
        $paths = 'docs/getting-started/installation,docs/getting-started/installation';
        $response = $this->getJson("/entries/doc/batch?slugs={$paths}");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Installation Guide');
    }

    public function test_returns_400_for_invalid_path_format()
    {
        $response = $this->getJson('/entries/doc/batch?slugs=docs/../../../etc/passwd');
        $response->assertStatus(400);

        $response = $this->getJson('/entries/doc/batch?slugs=docs/%00/injection');
        $response->assertStatus(400);
    }

    public function test_handles_deeply_nested_paths()
    {
        $paths = implode(',', [
            'docs/tutorials/deployment/cloud',
            'docs/tutorials/basic',
        ]);

        $response = $this->getJson("/entries/doc/batch?slugs={$paths}");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.title', 'Cloud Deployment')
            ->assertJsonPath('data.1.title', 'Basic Tutorial');
    }

    public function test_batch_retrieval_without_slugs()
    {
        $response = $this->getJson("/entries/doc/batch");

        $response->assertStatus(400)
            ->assertJson(['error' => 'The slugs field is required.']);
    }

    public function test_validates_path_security()
    {
        $maliciousPaths = [
            'docs/../secrets',
            'docs/./hidden',
            'docs//double-slash',
            'docs/%2e%2e/bypass',
            'docs/\backslash',
        ];

        foreach ($maliciousPaths as $path) {
            $response = $this->getJson("/entries/doc/batch?slugs={$path}");
            $response->assertStatus(400);
        }
    }

    public function test_batch_show_with_nonexistent_type()
    {
        $paths = 'docs/getting-started/installation,docs/getting-started/configuration';
        $response = $this->getJson("/entries/nonexistent/batch?slugs={$paths}");

        $response->assertStatus(404)
            ->assertJson(['error' => 'No items found for the specified type and slugs']);
    }

    public function test_batch_show_with_special_characters_in_slugs()
    {
        // Create test entries with special characters in slugs
        Entry::factory()->create([
            'type' => 'doc',
            'title' => 'Special Characters Page',
            'slug' => 'docs/special-characters',
        ]);

        Entry::factory()->create([
            'type' => 'doc',
            'title' => 'Underscore Page',
            'slug' => 'docs/under_score',
        ]);

        $paths = 'docs/special-characters,docs/under_score';
        $response = $this->getJson("/entries/doc/batch?slugs={$paths}");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.title', 'Special Characters Page')
            ->assertJsonPath('data.1.title', 'Underscore Page');
    }
}
