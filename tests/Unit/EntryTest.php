<?php

namespace Tests\Unit;

use App\Models\Entry;
use App\Rules\ValidPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_entry_normalizes_paths()
    {
        // Modify ValidPath validation temporarily to allow testing normalization
        $mock = $this->getMockBuilder(ValidPath::class)
            ->getMock();
        $mock->method('validate')
            ->willReturnCallback(function() {});

        $this->app->bind(ValidPath::class, function() use ($mock) {
            return $mock;
        });

        $paths = [
            // Basic path normalization
            'docs/getting-started.md' => 'docs/getting-started',
            'docs\\windows\\path.md' => 'docs/windows/path',
            '/leading/slash.md' => 'leading/slash',
            'trailing/slash.md/' => 'trailing/slash',
            '//double//slashes.md//' => 'double/slashes',
            'mixed\\slashes/path.md' => 'mixed/slashes/path',
            'special@#$chars.md' => 'special-chars',
            'no-extension' => 'no-extension',
            'spaces in path.md' => 'spaces-in-path',

            // Index path normalization - these should all reduce to their parent path
            'docs/getting-started/index.md' => 'docs/getting-started',
            'index.md' => '',  // Root index becomes empty string
            'docs/index.md' => 'docs',  // /index is removed
            'deeply/nested/path/index.md' => 'deeply/nested/path',
            'multiple///slashes/index.md' => 'multiple/slashes',
        ];

        foreach ($paths as $input => $expected) {
            $entry = new Entry(['type' => 'doc']);
            $entry->slug = $input;

            $this->assertEquals($expected, $entry->slug, "Path '$input' was not normalized correctly");
            $this->assertEquals($this->isIndexPath($input), $entry->is_index, "is_index not set correctly for: $input");
        }
    }

    public function test_entry_accepts_valid_paths()
    {
        $validPaths = [
            'docs/getting-started.md',
            'simple-file.md',
            'nested/path/to/file.md',
            'with-numbers123/path456.md',
            'with-dashes-and-underscores/file_name.md',
            'index.md',
            'docs/index.md',
            'deeply/nested/path/index.md',
        ];

        foreach ($validPaths as $path) {
            try {
                $entry = new Entry(['type' => 'doc']);
                $entry->slug = $path;
                $this->assertTrue(true, "Path '$path' should be accepted");
            } catch (\InvalidArgumentException $e) {
                $this->fail("Path '$path' should be accepted but failed with: " . $e->getMessage());
            }
        }
    }

    public function test_entry_hierarchy_relationships()
    {
        // Create a complete documentation structure
        Entry::factory()->create([
            'type' => 'doc',
            'slug' => 'docs',  // Root docs directory (was docs/index)
            'is_index' => true,
        ]);

        Entry::factory()->create([
            'type' => 'doc',
            'slug' => 'docs/getting-started',  // Getting started section (was getting-started/index)
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

        Entry::factory()->create([
            'type' => 'doc',
            'slug' => 'docs/advanced',  // Advanced section (was advanced/index)
            'is_index' => true,
        ]);

        // Test ancestors
        $ancestors = $installation->ancestors();
        $this->assertCount(2, $ancestors);
        $this->assertEquals(['docs', 'docs/getting-started'], $ancestors->pluck('slug')->toArray());

        // Test parent
        $parent = $installation->parent();
        $this->assertNotNull($parent);
        $this->assertEquals('docs/getting-started', $parent->slug);

        // Test siblings
        $siblings = $installation->siblings();
        $this->assertCount(1, $siblings);
        $this->assertEquals('docs/getting-started/configuration', $siblings->first()->slug);

        // Test children of a section
        $children = Entry::where('slug', 'docs/getting-started')->first()->children();
        $this->assertCount(2, $children);
        $this->assertEquals(
            ['docs/getting-started/configuration', 'docs/getting-started/installation'],
            $children->pluck('slug')->sort()->values()->toArray()
        );

        // Test breadcrumbs
        $breadcrumbs = $installation->breadcrumbs();
        $this->assertEquals(
            ['docs', 'docs/getting-started', 'docs/getting-started/installation'],
            $breadcrumbs->pluck('slug')->toArray()
        );
    }

    public function test_entry_queries()
    {
        // Create test structure
        Entry::factory()->create(['type' => 'doc', 'slug' => 'docs/getting-started', 'is_index' => true]);
        Entry::factory()->create(['type' => 'doc', 'slug' => 'docs/getting-started/installation', 'is_index' => false]);
        Entry::factory()->create(['type' => 'doc', 'slug' => 'docs/getting-started/configuration', 'is_index' => false]);
        Entry::factory()->create(['type' => 'doc', 'slug' => 'docs/advanced', 'is_index' => true]);
        Entry::factory()->create(['type' => 'doc', 'slug' => 'docs/advanced/deployment', 'is_index' => false]);

        // Find entries at specific level
        $gettingStartedEntries = Entry::query()
            ->where('type', 'doc')
            ->where('slug', 'like', 'docs/getting-started%')
            ->get();

        $this->assertCount(3, $gettingStartedEntries);
        $this->assertEqualsCanonicalizing(
            [
                'docs/getting-started',
                'docs/getting-started/installation',
                'docs/getting-started/configuration'
            ],
            $gettingStartedEntries->pluck('slug')->toArray()
        );

        // Find all entries under path
        $allEntries = Entry::query()
            ->where('type', 'doc')
            ->where('slug', 'like', 'docs%')
            ->get();

        $this->assertCount(5, $allEntries);
        $this->assertEqualsCanonicalizing(
            [
                'docs/getting-started',
                'docs/getting-started/installation',
                'docs/getting-started/configuration',
                'docs/advanced',
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
                'docs/getting-started',
                'docs/advanced'
            ],
            $indexFiles->pluck('slug')->toArray()
        );

        // Test path-based query
        $installation = Entry::where('slug', 'docs/getting-started/installation')->first();
        $siblings = $installation->siblings();
        $this->assertCount(1, $siblings);
        $this->assertEquals('docs/getting-started/configuration', $siblings->first()->slug);
    }

    public function test_root_index_handling()
    {
        // Create root index and some root-level entries
        Entry::factory()->create([
            'type' => 'doc',
            'slug' => '',  // Root index now has empty slug
            'is_index' => true,
        ]);

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

        // Test root children
        $rootChildren = Entry::where('slug', '')->first()->children();
        $this->assertEquals(
            ['about', 'contact'],
            $rootChildren->pluck('slug')->sort()->values()->toArray()
        );

        // Test root-level parent relationships
        $this->assertNull($about->parent());
        $this->assertNull($contact->parent());

        // Test root-level siblings
        $aboutSiblings = $about->siblings();
        $this->assertCount(1, $aboutSiblings);
        $this->assertEquals('contact', $aboutSiblings->first()->slug);

        // Test root-level ancestors (should be empty)
        $this->assertCount(0, $about->ancestors());
        $this->assertCount(0, $contact->ancestors());

        // Test deeply nested structure
        Entry::factory()->create([
            'type' => 'doc',
            'slug' => 'docs',
            'is_index' => true,
        ]);

        Entry::factory()->create([
            'type' => 'doc',
            'slug' => 'docs/section',
            'is_index' => true,
        ]);

        Entry::factory()->create([
            'type' => 'doc',
            'slug' => 'docs/section/subsection',
            'is_index' => true,
        ]);

        $deeplyNested = Entry::factory()->create([
            'type' => 'doc',
            'slug' => 'docs/section/subsection/page',
            'is_index' => false,
        ]);

        // Test ancestors of deeply nested page
        $this->assertEquals(
            ['docs', 'docs/section', 'docs/section/subsection'],
            $deeplyNested->ancestors()->pluck('slug')->toArray()
        );
    }

    private function isIndexPath(string $path): bool
    {
        return str_ends_with($path, '/index.md') || $path === 'index.md';
    }
}
