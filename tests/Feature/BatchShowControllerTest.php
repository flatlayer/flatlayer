<?php

namespace Tests\Feature;

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
            'is_index' => false,
        ]);

        // Create nested documentation structure
        Entry::factory()->create([
            'type' => 'doc',
            'title' => 'Getting Started Index',
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

        Entry::factory()->create([
            'type' => 'doc',
            'title' => 'Configuration Guide',
            'content' => 'Configuration instructions',
            'slug' => 'docs/getting-started/configuration',
            'is_index' => false,
        ]);

        // Create another section with both index and content files
        Entry::factory()->create([
            'type' => 'doc',
            'title' => 'Tutorials Section',
            'content' => 'Tutorial index content',
            'slug' => 'docs/tutorials/index',
            'is_index' => true,
        ]);

        Entry::factory()->create([
            'type' => 'doc',
            'title' => 'Basic Tutorial',
            'content' => 'Basic tutorial content',
            'slug' => 'docs/tutorials/basic',
            'is_index' => false,
        ]);

        Entry::factory()->create([
            'type' => 'doc',
            'title' => 'Advanced Tutorial',
            'content' => 'Advanced tutorial content',
            'slug' => 'docs/tutorials/advanced',
            'is_index' => false,
        ]);

        // Create deeply nested content
        Entry::factory()->create([
            'type' => 'doc',
            'title' => 'Cloud Deployment',
            'content' => 'Cloud deployment tutorial',
            'slug' => 'docs/tutorials/deployment/cloud/index',
            'is_index' => true,
        ]);
    }

    public function test_can_retrieve_multiple_entries_by_paths()
    {
        $paths = 'docs/getting-started/installation,docs/getting-started/configuration';
        $response = $this->getJson("/entry/batch/doc?slugs={$paths}");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.title', 'Installation Guide')
            ->assertJsonPath('data.1.title', 'Configuration Guide')
            ->assertJsonPath('data.0.is_index', false)
            ->assertJsonPath('data.1.is_index', false);
    }

    public function test_batch_retrieval_with_index_files()
    {
        $paths = 'docs/tutorials/index,docs/tutorials/deployment/cloud/index';
        $response = $this->getJson("/entry/batch/doc?slugs={$paths}");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.title', 'Tutorials Section')
            ->assertJsonPath('data.1.title', 'Cloud Deployment')
            ->assertJsonPath('data.0.is_index', true)
            ->assertJsonPath('data.1.is_index', true);
    }

    public function test_batch_show_respects_fields_parameter_with_nested_content()
    {
        $paths = 'docs/getting-started/installation,docs/getting-started/configuration';
        $fields = json_encode(['title', 'slug', 'is_index']);

        $response = $this->getJson("/entry/batch/doc?slugs={$paths}&fields={$fields}");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [
                    ['title', 'slug', 'is_index'],
                    ['title', 'slug', 'is_index'],
                ],
            ])
            ->assertJsonMissing(['content']);
    }

    public function test_handles_mixed_regular_and_index_paths()
    {
        $paths = 'docs/tutorials/basic,docs/tutorials/index';
        $response = $this->getJson("/entry/batch/doc?slugs={$paths}");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.title', 'Basic Tutorial')
            ->assertJsonPath('data.0.is_index', false)
            ->assertJsonPath('data.1.title', 'Tutorials Section')
            ->assertJsonPath('data.1.is_index', true);
    }

    public function test_handles_directory_to_index_redirects()
    {
        $paths = 'docs/tutorials,docs/getting-started';
        $response = $this->getJson("/entry/batch/doc?slugs={$paths}");

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => [
                ['redirect', 'from', 'to', 'location', 'is_index'],
                ['redirect', 'from', 'to', 'location', 'is_index'],
            ]])
            ->assertJson([
                'data' => [
                    [
                        'redirect' => true,
                        'from' => 'docs/tutorials',
                        'to' => 'docs/tutorials/index',
                        'location' => '/entry/doc/docs/tutorials/index',
                    ],
                    [
                        'redirect' => true,
                        'from' => 'docs/getting-started',
                        'to' => 'docs/getting-started/index',
                        'location' => '/entry/doc/docs/getting-started/index',
                    ],
                ],
            ]);
    }

    public function test_handles_mixed_paths_and_redirects()
    {
        $paths = 'docs/tutorials/basic,docs/tutorials,docs/tutorials/advanced';
        $response = $this->getJson("/entry/batch/doc?slugs={$paths}");

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.title', 'Basic Tutorial')
            ->assertJsonPath('data.1.redirect', true)
            ->assertJsonPath('data.2.title', 'Advanced Tutorial');
    }

    public function test_returns_404_for_non_existent_nested_paths()
    {
        $paths = 'docs/getting-started/installation,docs/nonexistent/path';
        $response = $this->getJson("/entry/batch/doc?slugs={$paths}");

        $response->assertStatus(404)
            ->assertJson(['error' => 'No items found for the specified type and slugs']);
    }

    public function test_maintains_order_with_nested_paths()
    {
        $paths = 'docs/tutorials/advanced,docs/getting-started/installation,docs/tutorials/basic';
        $response = $this->getJson("/entry/batch/doc?slugs={$paths}");

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.title', 'Advanced Tutorial')
            ->assertJsonPath('data.1.title', 'Installation Guide')
            ->assertJsonPath('data.2.title', 'Basic Tutorial');
    }

    public function test_handles_duplicate_nested_paths()
    {
        $paths = 'docs/getting-started/installation,docs/getting-started/installation';
        $response = $this->getJson("/entry/batch/doc?slugs={$paths}");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Installation Guide');
    }

    public function test_returns_400_for_invalid_path_format()
    {
        $response = $this->getJson('/entry/batch/doc?slugs=docs/../../../etc/passwd');
        $response->assertStatus(400);

        $response = $this->getJson('/entry/batch/doc?slugs=docs/%00/injection');
        $response->assertStatus(400);
    }

    public function test_handles_deeply_nested_paths()
    {
        $paths = implode(',', [
            'docs/tutorials/deployment/cloud/index',
            'docs/tutorials/basic',
        ]);

        $response = $this->getJson("/entry/batch/doc?slugs={$paths}");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.title', 'Cloud Deployment')
            ->assertJsonPath('data.0.is_index', true)
            ->assertJsonPath('data.1.title', 'Basic Tutorial')
            ->assertJsonPath('data.1.is_index', false);
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
            $response = $this->getJson("/entry/batch/doc?slugs={$path}");
            $response->assertStatus(400);
        }
    }
}
