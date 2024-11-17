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
            'docs/getting-started.md' => 'docs/getting-started',
            'docs\\windows\\path.md' => 'docs/windows/path',
            '/leading/slash.md' => 'leading/slash',
            'trailing/slash.md/' => 'trailing/slash',
            '//double//slashes.md//' => 'double/slashes',
            'mixed\\slashes/path.md' => 'mixed/slashes/path',

            // Index path handling
            'docs/getting-started/index.md' => 'docs/getting-started/index',
            'index.md' => 'index',
            'docs/index.md' => 'docs/index',
        ];

        foreach ($paths as $input => $expected) {
            $entry = new Entry(['type' => 'doc']);
            $entry->slug = $input;

            $this->assertEquals($expected, $entry->slug, "Path '$input' was not normalized correctly");
            $this->assertEquals(str_ends_with($input, 'index.md'), $entry->is_index, "is_index not set correctly for: $input");
        }
    }

    public function test_entry_rejects_invalid_paths()
    {
        $invalidPaths = [
            // Path traversal attempts
            '../path/traversal.md' => 'Path traversal not allowed.',
            './current/directory.md' => 'Path traversal not allowed.',
            'path/../traversal.md' => 'Path traversal not allowed.',

            // Encoded separators
            'path%2e%2e/encoded.md' => 'Encoded path separators not allowed.',
            'path%2fencoded/slash.md' => 'Encoded path separators not allowed.',

            // Invalid characters
            'path/with/*/asterisk.md' => 'Invalid characters in path.',
            'path/with/>/angle.md' => 'Invalid characters in path.',
            'path/with/:/colon.md' => 'Invalid characters in path.',
            'path/with/|/pipe.md' => 'Invalid characters in path.',
            'path/with/"/quote.md' => 'Invalid characters in path.',
            'path/with/?/question.md' => 'Invalid characters in path.',
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
            'slug' => 'docs/index',
            'is_index' => true,
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
            ['docs/index', 'docs/getting-started/index'],
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
            ['docs/index', 'docs/getting-started/index', 'docs/getting-started/installation'],
            $installation->breadcrumbs()->pluck('slug')->toArray()
        );
    }

    public function test_entry_queries()
    {
        // Create test structure
        Entry::factory()->create(['type' => 'doc', 'slug' => 'docs/getting-started/index', 'is_index' => true]);
        Entry::factory()->create(['type' => 'doc', 'slug' => 'docs/getting-started/installation', 'is_index' => false]);
        Entry::factory()->create(['type' => 'doc', 'slug' => 'docs/getting-started/configuration', 'is_index' => false]);
        Entry::factory()->create(['type' => 'doc', 'slug' => 'docs/advanced/index', 'is_index' => true]);
        Entry::factory()->create(['type' => 'doc', 'slug' => 'docs/advanced/deployment', 'is_index' => false]);

        // Find entries at specific level - includes index and all files in directory
        $gettingStartedEntries = Entry::query()
            ->where('type', 'doc')
            ->where('slug', 'like', 'docs/getting-started/%')
            ->get();
        $this->assertCount(3, $gettingStartedEntries);
        $this->assertEqualsCanonicalizing(
            [
                'docs/getting-started/index',
                'docs/getting-started/installation',
                'docs/getting-started/configuration'
            ],
            $gettingStartedEntries->pluck('slug')->toArray()
        );

        // Find all entries under path
        $allEntries = Entry::query()
            ->where('type', 'doc')
            ->where('slug', 'like', 'docs/%')
            ->get();
        $this->assertCount(5, $allEntries);
        $this->assertEqualsCanonicalizing(
            [
                'docs/getting-started/index',
                'docs/getting-started/installation',
                'docs/getting-started/configuration',
                'docs/advanced/index',
                'docs/advanced/deployment'
            ],
            $allEntries->pluck('slug')->toArray()
        );

        // Find only index files
        $indexFiles = Entry::query()
            ->where('type', 'doc')
            ->where('is_index', true)
            ->get();
        $this->assertCount(2, $indexFiles);
        $this->assertEqualsCanonicalizing(
            [
                'docs/getting-started/index',
                'docs/advanced/index'
            ],
            $indexFiles->pluck('slug')->toArray()
        );

        // Find siblings
        $installation = Entry::where('slug', 'docs/getting-started/installation')->first();
        $siblings = $installation->siblings();
        $this->assertCount(1, $siblings);
        $this->assertEquals('docs/getting-started/configuration', $siblings->first()->slug);
    }

    public function test_root_index_handling()
    {
        // Test root index.md
        $root = Entry::factory()->create([
            'type' => 'doc',
            'slug' => 'index',
            'is_index' => true,
        ]);

        // Test nested root level files
        $about = Entry::factory()->create([
            'type' => 'doc',
            'slug' => 'about',
            'is_index' => false,
        ]);

        $contact = Entry::factory()->create([
            'type' => 'doc',
            'slug' => 'contact',
            'is_index' => false,
        ]);

        // Test that root index has correct children
        $this->assertEquals(
            ['about', 'contact'],
            $root->children()->pluck('slug')->sort()->values()->toArray()
        );

        // Test that root level files have no parent
        $this->assertNull($about->parent());
        $this->assertNull($contact->parent());

        // Test that root level files are siblings
        $this->assertEquals(
            ['contact'],
            $about->siblings()->pluck('slug')->toArray()
        );
    }
}
