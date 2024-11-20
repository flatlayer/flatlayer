<?php

namespace Tests\Feature\Controllers;

use App\Models\Entry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupTestContent();
    }

    protected function setupTestContent(): void
    {
        // Create root level entry
        Entry::factory()->create([
            'type' => 'doc',
            'title' => 'Documentation',
            'content' => 'Main documentation content',
            'excerpt' => 'Documentation root overview',
            'slug' => 'docs',
            'meta' => [
                'section' => 'root',
                'nav_order' => 1,
            ],
            'published_at' => now()->subDays(30), // Oldest
        ]);

        // Create getting started section
        Entry::factory()->create([
            'type' => 'doc',
            'title' => 'Getting Started',
            'content' => 'Getting started guide content',
            'excerpt' => 'Begin your journey here',
            'slug' => 'docs/getting-started',
            'meta' => [
                'section' => 'guides',
                'nav_order' => 2,
            ],
            'published_at' => now()->subDays(20),
        ]);

        // Create installation guide
        Entry::factory()->create([
            'type' => 'doc',
            'title' => 'Installation Guide',
            'content' => 'Installation instructions content',
            'excerpt' => 'Learn how to install the software',
            'slug' => 'docs/getting-started/installation',
            'meta' => [
                'section' => 'guides',
                'nav_order' => 2,
            ],
            'published_at' => now()->subDays(5),
        ]);

        // Create configuration guide with tags
        $taggedEntry = Entry::factory()->create([
            'type' => 'doc',
            'title' => 'Configuration',
            'content' => 'Configuration guide content',
            'excerpt' => 'Configure your installation',
            'slug' => 'docs/getting-started/configuration',
            'meta' => [
                'section' => 'guides',
                'nav_order' => 1,
            ],
            'published_at' => now()->subDays(10),
        ]);
        $taggedEntry->attachTags(['setup', 'configuration']);
    }

    public function test_can_retrieve_single_entry()
    {
        $response = $this->getJson('/entry/doc/docs/getting-started');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'title',
                'content',
                'slug',
                'meta',
                'type',
            ])
            ->assertJsonPath('title', 'Getting Started')
            ->assertJsonPath('slug', 'docs/getting-started');
    }

    public function test_returns_404_for_nonexistent_entry()
    {
        $response = $this->getJson('/entry/doc/docs/nonexistent');
        $response->assertStatus(404);
    }

    public function test_returns_404_for_wrong_type()
    {
        $response = $this->getJson('/entry/post/docs/getting-started');
        $response->assertStatus(404);
    }

    public function test_respects_fields_parameter()
    {
        $fields = json_encode(['title', 'slug']);
        $response = $this->getJson("/entry/doc/docs/getting-started?fields={$fields}");

        $response->assertStatus(200)
            ->assertJsonStructure(['title', 'slug'])
            ->assertJsonMissing(['content', 'meta']);
    }

    public function test_includes_tags_when_present()
    {
        $response = $this->getJson('/entry/doc/docs/getting-started/configuration');

        $response->assertStatus(200)
            ->assertJsonStructure(['tags'])
            ->assertJsonPath('tags', ['setup', 'configuration']);
    }

    public function test_handles_nested_meta_fields()
    {
        $fields = json_encode(['title', 'meta.section']);
        $response = $this->getJson("/entry/doc/docs/getting-started?fields={$fields}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'title',
                'meta' => ['section'],
            ])
            ->assertJsonPath('meta.section', 'guides');
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
            $response = $this->getJson("/entry/doc/{$path}");
            $response->assertStatus(400);
        }
    }

    public function test_includes_navigation_features_with_correct_fields(): void
    {
        // Request all navigation features and some extra fields
        $includes = 'hierarchy,sequence,timeline';
        $navFields = json_encode(['meta.section', 'published_at']);

        $response = $this->getJson("/entry/doc/docs/getting-started/installation?includes={$includes}&navigation_fields={$navFields}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'type',
                'title',
                'slug',
                'content',
                'excerpt',
                'published_at',
                'meta' => [
                    'section',
                    'nav_order',
                ],
                'tags',
                'images',
                'hierarchy' => [
                    'ancestors' => [
                        '*' => [
                            'title',
                            'slug',
                            'excerpt',
                            'meta' => ['section'],
                            'published_at',
                        ],
                    ],
                    'siblings' => [
                        '*' => [
                            'title',
                            'slug',
                            'excerpt',
                            'meta' => ['section'],
                            'published_at',
                        ],
                    ],
                    'children',
                ],
                'sequence' => [
                    'previous' => [
                        'title',
                        'slug',
                        'excerpt',
                        'meta' => ['section'],
                        'published_at',
                    ],
                    'position' => [
                        'current',
                        'total',
                    ],
                ],
                'timeline' => [
                    'previous' => [
                        'title',
                        'slug',
                        'excerpt',
                        'meta' => ['section'],
                        'published_at',
                    ],
                    'position' => [
                        'current',
                        'total',
                    ],
                ],
            ]);

        // Verify main entry data
        $response->assertJson([
            'title' => 'Installation Guide',
            'slug' => 'docs/getting-started/installation',
            'excerpt' => 'Learn how to install the software',
            'meta' => [
                'section' => 'guides',
                'nav_order' => 2,
            ],
            'tags' => [],
            'images' => [],
        ]);

        // Verify ancestors
        $response->assertJsonPath('hierarchy.ancestors.0.title', 'Documentation')
            ->assertJsonPath('hierarchy.ancestors.0.meta.section', 'root')
            ->assertJsonPath('hierarchy.ancestors.1.title', 'Getting Started')
            ->assertJsonPath('hierarchy.ancestors.1.meta.section', 'guides');

        // Verify siblings
        $siblings = $response->json('hierarchy.siblings');
        $this->assertCount(1, $siblings);
        $this->assertEquals([
            'title' => 'Configuration',
            'slug' => 'docs/getting-started/configuration',
            'excerpt' => 'Configure your installation',
            'meta' => ['section' => 'guides'],
            'published_at' => $siblings[0]['published_at'],  // Dynamic timestamp
        ], $siblings[0]);

        // Verify sequence navigation
        $this->assertEquals('Configuration', $response->json('sequence.previous.title'));
        $this->assertNull($response->json('sequence.next'));
        $this->assertEquals(2, $response->json('sequence.position.current'));
        $this->assertEquals(2, $response->json('sequence.position.total'));

        // Verify timeline navigation
        $this->assertEquals('Configuration', $response->json('timeline.previous.title'));
        $this->assertNull($response->json('timeline.next'));
        $this->assertEquals(4, $response->json('timeline.position.current'));
        $this->assertEquals(4, $response->json('timeline.position.total'));

        // Verify all navigation entries have the required fields
        foreach (['hierarchy.ancestors.0', 'hierarchy.ancestors.1', 'hierarchy.siblings.0', 'sequence.previous', 'timeline.previous'] as $path) {
            $entry = $response->json($path);
            $this->assertArrayHasKey('title', $entry);
            $this->assertArrayHasKey('slug', $entry);
            $this->assertArrayHasKey('excerpt', $entry);
            $this->assertArrayHasKey('meta', $entry);
            $this->assertArrayHasKey('section', $entry['meta']);
            $this->assertArrayHasKey('published_at', $entry);
        }
    }
}
