<?php

namespace Tests\Feature;

use App\Models\Entry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ListControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupHierarchicalContent();
    }

    protected function setupHierarchicalContent()
    {
        // Create root level entries
        Entry::factory()->atPath('docs')->asIndex()->create([
            'title' => 'Documentation',
            'type' => 'doc',
            'meta' => ['section' => 'root'],
        ]);

        // Create nested documentation structure
        Entry::factory()->atPath('docs/getting-started')->asIndex()->create([
            'title' => 'Getting Started',
            'type' => 'doc',
            'meta' => ['section' => 'intro'],
        ]);

        Entry::factory()->atPath('docs/getting-started/installation')->create([
            'title' => 'Installation Guide',
            'type' => 'doc',
            'meta' => ['difficulty' => 'beginner'],
        ]);

        Entry::factory()->atPath('docs/getting-started/configuration')->create([
            'title' => 'Configuration',
            'type' => 'doc',
            'meta' => ['difficulty' => 'intermediate'],
        ]);

        // Create another section with nested content
        Entry::factory()->atPath('docs/advanced')->asIndex()->create([
            'title' => 'Advanced Topics',
            'type' => 'doc',
            'meta' => ['section' => 'advanced'],
        ]);

        Entry::factory()->atPath('docs/advanced/deployment')->create([
            'title' => 'Deployment Guide',
            'type' => 'doc',
            'meta' => ['difficulty' => 'advanced'],
        ]);

        // Create some blog posts for mixed content testing
        Entry::factory()->atPath('blog')->asIndex()->create([
            'title' => 'Blog',
            'type' => 'post',
            'meta' => ['section' => 'blog'],
        ]);

        Entry::factory()->atPath('blog/first-post')->create([
            'title' => 'First Blog Post',
            'type' => 'post',
            'published_at' => now()->subDays(5),
        ]);

        Entry::factory()->atPath('blog/second-post')->create([
            'title' => 'Second Blog Post',
            'type' => 'post',
            'published_at' => now()->subDays(3),
        ]);
    }

    public function test_index_returns_paginated_nested_results()
    {
        // Create additional nested content for pagination testing
        for ($i = 1; $i <= 20; $i++) {
            Entry::factory()->atPath("docs/section-{$i}/page")->create([
                'title' => "Page {$i}",
                'type' => 'doc',
            ]);
        }

        $response = $this->getJson('/entry/doc');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'pagination' => [
                    'current_page',
                    'total_pages',
                    'per_page',
                ],
            ])
            ->assertJsonCount(15, 'data'); // Default per_page is 15
    }

    public function test_index_respects_per_page_with_nested_content()
    {
        // Create nested content
        for ($i = 1; $i <= 20; $i++) {
            Entry::factory()->atPath("docs/section-{$i}")->create([
                'title' => "Section {$i}",
                'type' => 'doc',
            ]);
        }

        $response = $this->getJson('/entry/doc?per_page=10');

        $response->assertStatus(200)
            ->assertJsonCount(10, 'data');
    }

    public function test_hierarchical_listing()
    {
        $filter = json_encode([
            '$hierarchy' => [
                'descendants' => 'docs/getting-started',
            ],
        ]);

        $response = $this->getJson("/entry/doc?filter={$filter}");

        $response->assertStatus(200)
            ->assertJsonFragment(['title' => 'Installation Guide'])
            ->assertJsonFragment(['title' => 'Configuration'])
            ->assertJsonMissing(['title' => 'Deployment Guide']);
    }

    public function test_listing_siblings()
    {
        $filter = json_encode([
            'slug' => [
                '$isSiblingOf' => 'docs/getting-started/installation',
            ],
        ]);

        $response = $this->getJson("/entry/doc?filter={$filter}");

        $response->assertStatus(200)
            ->assertJsonFragment(['title' => 'Configuration'])
            ->assertJsonMissing(['title' => 'Deployment Guide']);
    }

    public function test_listing_with_path_based_filters()
    {
        $filter = json_encode([
            'slug' => [
                '$startsWith' => 'docs/advanced',
            ],
        ]);

        $response = $this->getJson("/entry/doc?filter={$filter}");

        $response->assertStatus(200)
            ->assertJsonFragment(['title' => 'Advanced Topics'])
            ->assertJsonFragment(['title' => 'Deployment Guide'])
            ->assertJsonMissing(['title' => 'Installation Guide']);
    }

    public function test_combined_path_and_meta_filters()
    {
        $filter = json_encode([
            'slug' => ['$startsWith' => 'docs/'],
            'meta.difficulty' => 'advanced',
        ]);

        $response = $this->getJson("/entry/doc?filter={$filter}");

        $response->assertStatus(200)
            ->assertJsonFragment(['title' => 'Deployment Guide'])
            ->assertJsonMissing(['title' => 'Installation Guide'])
            ->assertJsonMissing(['title' => 'Configuration']);
    }

    public function test_filtering_by_parent()
    {
        $filter = json_encode([
            'slug' => [
                '$hasParent' => 'docs/getting-started',
            ],
        ]);

        $response = $this->getJson("/entry/doc?filter={$filter}");

        $response->assertStatus(200)
            ->assertJsonFragment(['title' => 'Installation Guide'])
            ->assertJsonFragment(['title' => 'Configuration'])
            ->assertJsonMissing(['title' => 'Deployment Guide']);
    }

    public function test_mixed_hierarchical_and_type_filtering()
    {
        $filter = json_encode([
            '$and' => [
                [
                    'slug' => ['$startsWith' => 'docs/'],
                    'meta.difficulty' => ['$in' => ['beginner', 'intermediate']],
                ],
            ],
        ]);

        $response = $this->getJson("/entry/doc?filter={$filter}");

        $response->assertStatus(200)
            ->assertJsonFragment(['title' => 'Installation Guide'])
            ->assertJsonFragment(['title' => 'Configuration'])
            ->assertJsonMissing(['title' => 'Deployment Guide']);
    }

    public function test_index_returns_404_for_invalid_type()
    {
        $response = $this->getJson('/entry/invalid-type');
        $response->assertStatus(404);
    }

    public function test_index_applies_tag_filters_with_hierarchy()
    {
        $entry = Entry::factory()->atPath('docs/getting-started/tutorial')->create([
            'title' => 'Tutorial',
            'type' => 'doc',
        ]);
        $entry->attachTag('beginner');

        $filter = json_encode([
            '$tags' => ['beginner'],
            'slug' => ['$startsWith' => 'docs/getting-started'],
        ]);

        $response = $this->getJson("/entry/doc?filter={$filter}");

        $response->assertStatus(200)
            ->assertJsonFragment(['title' => 'Tutorial'])
            ->assertJsonMissing(['title' => 'Deployment Guide']);
    }

    public function test_nested_meta_fields_with_hierarchy()
    {
        Entry::factory()->atPath('docs/reference/api')->create([
            'title' => 'API Reference',
            'type' => 'doc',
            'meta' => [
                'version' => '2.0',
                'api' => [
                    'stability' => 'stable',
                    'auth' => ['token', 'oauth'],
                ],
            ],
        ]);

        $fields = json_encode(['title', 'meta.version', 'meta.api.stability']);
        $filter = json_encode(['slug' => ['$startsWith' => 'docs/reference']]);

        $response = $this->getJson("/entry/doc?filter={$filter}&fields={$fields}");

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => [[
                'title',
                'meta' => [
                    'version',
                    'api' => ['stability'],
                ],
            ]]])
            ->assertJsonMissing(['meta' => ['api' => ['auth']]]);
    }

    public function test_hierarchical_ordering()
    {
        $filter = json_encode([
            'slug' => ['$startsWith' => 'docs/'],
            '$order' => ['slug' => 'asc'],
        ]);

        $response = $this->getJson("/entry/doc?filter={$filter}");

        $data = $response->json('data');
        $slugs = collect($data)->pluck('slug')->values()->all();

        // Verify that the slugs are properly ordered
        $this->assertEquals(
            collect($slugs)->sort()->values()->all(),
            $slugs,
            'Entries should be ordered by slug'
        );
    }
}
