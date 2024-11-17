<?php

namespace Tests\Unit;

use App\Models\Entry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_entry_normalizes_paths()
    {
        $paths = [
            // Basic path normalization
            'docs/getting-started' => 'docs/getting-started',
            'docs\\windows\\path' => 'docs/windows/path',
            '/leading/slash' => 'leading/slash',
            'trailing/slash/' => 'trailing/slash',
            '//double//slashes//' => 'double/slashes',
            'mixed\\slashes/path' => 'mixed/slashes/path',

            // Index path handling
            'docs/getting-started/index' => 'docs/getting-started/index',
            'index' => 'index',
        ];

        foreach ($paths as $input => $expected) {
            $entry = new Entry(['type' => 'doc']);
            $entry->slug = $input;

            $this->assertEquals($expected, $entry->slug, "Path '$input' was not normalized correctly");
        }
    }

    public function test_entry_rejects_invalid_paths()
    {
        $invalidPaths = [
            // Path traversal attempts
            '../path/traversal' => 'Path traversal not allowed.',
            './current/directory' => 'Path traversal not allowed.',
            'path/../traversal' => 'Path traversal not allowed.',

            // Encoded separators
            'path%2e%2e/encoded' => 'Encoded path separators not allowed.',
            'path%2fencoded/slash' => 'Encoded path separators not allowed.',

            // Invalid characters
            'path/with/*/asterisk' => 'Invalid characters in path.',
            'path/with/>/angle' => 'Invalid characters in path.',
            'path/with/:/colon' => 'Invalid characters in path.',
            'path/with/|/pipe' => 'Invalid characters in path.',
            'path/with/"/quote' => 'Invalid characters in path.',
            'path/with/?/question' => 'Invalid characters in path.',
        ];

        foreach ($invalidPaths as $path => $expectedMessage) {
            try {
                $entry = new Entry(['type' => 'doc']);
                $entry->slug = $path;
                $this->fail("Expected path validation to fail for: $path");
            } catch (\InvalidArgumentException $e) {
                $this->assertEquals($expectedMessage, $e->getMessage(), "Wrong error message for path: $path");
            }
        }
    }

    public function test_entry_hierarchy_relationships()
    {
        // Create a complete documentation structure
        $root = Entry::factory()->create([
            'type' => 'doc',
            'slug' => 'docs',
            'is_index' => false,
        ]);

        $gettingStarted = Entry::factory()->create([
            'type' => 'doc',
            'slug' => 'docs/getting-started/index',
            'is_index' => true,
        ]);

        $installation = Entry::factory()->create([
            'type' => 'doc',
            'slug' => 'docs/getting-started/installation',
            'is_index' => false,
        ]);

        $configuration = Entry::factory()->create([
            'type' => 'doc',
            'slug' => 'docs/getting-started/configuration',
            'is_index' => false,
        ]);

        $advanced = Entry::factory()->create([
            'type' => 'doc',
            'slug' => 'docs/advanced/index',
            'is_index' => true,
        ]);

        // Test ancestors
        $this->assertEquals(
            ['docs', 'docs/getting-started/index'],
            $installation->ancestors()->pluck('slug')->toArray()
        );

        // Test siblings (should exclude index files)
        $this->assertEquals(
            ['docs/getting-started/configuration'],
            $installation->siblings()->pluck('slug')->toArray()
        );

        // Test children (should work for both regular and index files)
        $this->assertEquals(
            ['docs/getting-started/configuration', 'docs/getting-started/installation'],
            $gettingStarted->children()->pluck('slug')->sort()->values()->toArray()
        );

        // Test breadcrumbs (should include self)
        $this->assertEquals(
            ['docs', 'docs/getting-started/index', 'docs/getting-started/installation'],
            $installation->breadcrumbs()->pluck('slug')->toArray()
        );
    }

    public function test_entry_queries()
    {
        // Create test structure
        Entry::factory()->create(['type' => 'doc', 'slug' => 'docs/getting-started/index', 'is_index' => true]);
        Entry::factory()->create(['type' => 'doc', 'slug' => 'docs/getting-started/installation', 'is_index' => false]);
        Entry::factory()->create(['type' => 'doc', 'slug' => 'docs/advanced/index', 'is_index' => true]);
        Entry::factory()->create(['type' => 'doc', 'slug' => 'docs/advanced/deployment', 'is_index' => false]);

        // Find entries at specific level
        $this->assertCount(2, Entry::query()
            ->where('type', 'doc')
            ->where('slug', 'like', 'docs/getting-started/%')
            ->get()
        );

        // Find all entries under path
        $this->assertCount(4, Entry::query()
            ->where('type', 'doc')
            ->where('slug', 'like', 'docs/%')
            ->get()
        );

        // Find only index files
        $this->assertCount(2, Entry::query()
            ->where('type', 'doc')
            ->where('is_index', true)
            ->get()
        );

        // Find siblings
        $installation = Entry::where('slug', 'docs/getting-started/installation')->first();
        $this->assertCount(0, $installation->siblings()
            ->where('is_index', true)
            ->get()
        );
    }

    public function test_entry_path_conflict_resolution()
    {
        // Test basic conflict
        $original = Entry::factory()->create([
            'type' => 'doc',
            'slug' => 'docs/test',
            'is_index' => false,
        ]);

        $conflict = Entry::factory()->create([
            'type' => 'doc',
            'slug' => 'docs/test',
            'is_index' => false,
        ]);

        $this->assertNotEquals($original->slug, $conflict->slug);
        $this->assertMatchesRegularExpression('/^docs\/test-\d+$/', $conflict->slug);

        // Test index and regular file coexistence
        $index = Entry::factory()->create([
            'type' => 'doc',
            'slug' => 'docs/section/index',
            'is_index' => true,
        ]);

        $regular = Entry::factory()->create([
            'type' => 'doc',
            'slug' => 'docs/section',
            'is_index' => false,
        ]);

        // Both should exist with their original slugs
        $this->assertEquals('docs/section/index', $index->slug);
        $this->assertEquals('docs/section', $regular->slug);
    }
}
