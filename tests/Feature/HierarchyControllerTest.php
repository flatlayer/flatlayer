<?php

namespace Tests\Feature;

use App\Models\Entry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HierarchyControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupTestHierarchy();
    }

    protected function setupTestHierarchy(): void
    {
        // Create root level
        Entry::factory()->create([
            'type' => 'doc',
            'title' => 'Documentation',
            'slug' => 'docs',
        ]);

        // Create nested entries
        Entry::factory()->create([
            'type' => 'doc',
            'title' => 'Getting Started',
            'slug' => 'docs/getting-started',
        ]);

        Entry::factory()->create([
            'type' => 'doc',
            'title' => 'Installation',
            'slug' => 'docs/getting-started/installation',
        ]);
    }

    public function test_index_returns_hierarchy(): void
    {
        $response = $this->getJson('/hierarchy/doc');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'title',
                        'slug',
                        'children',
                    ],
                ],
                'meta' => [
                    'type',
                    'root',
                    'depth',
                    'total_nodes',
                ],
            ])
            ->assertJsonPath('meta.total_nodes', 3);
    }

    public function test_find_returns_node_and_ancestry(): void
    {
        $response = $this->getJson('/hierarchy/doc/docs/getting-started/installation');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'title',
                    'slug',
                    'meta',
                ],
                'meta' => [
                    'ancestry' => [
                        '*' => [
                            'id',
                            'title',
                            'slug',
                            'meta',
                            'children',
                        ],
                    ],
                    'depth',
                ],
            ])
            ->assertJsonPath('data.title', 'Installation')
            ->assertJsonPath('data.slug', 'docs/getting-started/installation');
    }

    public function test_find_returns_404_for_non_existent_node(): void
    {
        $response = $this->getJson('/hierarchy/doc/non/existent/path');

        $response->assertStatus(404)
            ->assertJson(['error' => 'Node not found']);
    }

    public function test_index_returns_404_for_invalid_type(): void
    {
        $response = $this->getJson('/hierarchy/invalid-type');

        $response->assertStatus(404);
    }
}
