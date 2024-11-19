<?php

namespace Tests\Feature;

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
        // Create root level entries
        Entry::factory()->create([
            'type' => 'doc',
            'title' => 'Documentation',
            'content' => 'Main documentation content',
            'slug' => 'docs',
            'meta' => ['section' => 'root'],
        ]);

        // Create nested entries
        Entry::factory()->create([
            'type' => 'doc',
            'title' => 'Getting Started',
            'content' => 'Getting started guide content',
            'slug' => 'docs/getting-started',
            'meta' => ['section' => 'guides'],
        ]);

        Entry::factory()->create([
            'type' => 'doc',
            'title' => 'Installation Guide',
            'content' => 'Installation instructions content',
            'slug' => 'docs/getting-started/installation',
            'meta' => ['section' => 'guides'],
        ]);

        // Create entry with tags
        $taggedEntry = Entry::factory()->create([
            'type' => 'doc',
            'title' => 'Configuration',
            'content' => 'Configuration guide content',
            'slug' => 'docs/getting-started/configuration',
            'meta' => ['section' => 'guides'],
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
}
