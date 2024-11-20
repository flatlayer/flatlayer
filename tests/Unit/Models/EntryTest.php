<?php

namespace Tests\Unit\Models;

use App\Models\Entry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_entry_hierarchy_relationships()
    {
        // Create a complete documentation structure
        Entry::factory()->create([
            'type' => 'doc',
            'slug' => 'docs',
        ]);

        Entry::factory()->create([
            'type' => 'doc',
            'slug' => 'docs/getting-started',
        ]);

        $installation = Entry::factory()->create([
            'type' => 'doc',
            'slug' => 'docs/getting-started/installation',
        ]);

        $configuration = Entry::factory()->create([
            'type' => 'doc',
            'slug' => 'docs/getting-started/configuration',
        ]);

        Entry::factory()->create([
            'type' => 'doc',
            'slug' => 'docs/advanced',
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
        Entry::factory()->create(['type' => 'doc', 'slug' => 'docs/getting-started']);
        Entry::factory()->create(['type' => 'doc', 'slug' => 'docs/getting-started/installation']);
        Entry::factory()->create(['type' => 'doc', 'slug' => 'docs/getting-started/configuration']);
        Entry::factory()->create(['type' => 'doc', 'slug' => 'docs/advanced']);
        Entry::factory()->create(['type' => 'doc', 'slug' => 'docs/advanced/deployment']);

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
                'docs/getting-started/configuration',
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
                'docs/advanced/deployment',
            ],
            $allEntries->pluck('slug')->toArray()
        );

        // Test path-based query
        $installation = Entry::where('slug', 'docs/getting-started/installation')->first();
        $siblings = $installation->siblings();
        $this->assertCount(1, $siblings);
        $this->assertEquals('docs/getting-started/configuration', $siblings->first()->slug);
    }

    public function test_root_level_entries()
    {
        // Create root-level entries
        $about = Entry::factory()->create([
            'type' => 'doc',
            'slug' => 'about',
        ]);

        $contact = Entry::factory()->create([
            'type' => 'doc',
            'slug' => 'contact',
        ]);

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
    }

    public function test_deeply_nested_entries()
    {
        Entry::factory()->create([
            'type' => 'doc',
            'slug' => 'docs',
        ]);

        Entry::factory()->create([
            'type' => 'doc',
            'slug' => 'docs/section',
        ]);

        Entry::factory()->create([
            'type' => 'doc',
            'slug' => 'docs/section/subsection',
        ]);

        $deeplyNested = Entry::factory()->create([
            'type' => 'doc',
            'slug' => 'docs/section/subsection/page',
        ]);

        // Test ancestors of deeply nested page
        $this->assertEquals(
            ['docs', 'docs/section', 'docs/section/subsection'],
            $deeplyNested->ancestors()->pluck('slug')->toArray()
        );
    }

    public function test_ordering_with_numeric_prefixes()
    {
        Entry::factory()->create(['type' => 'doc', 'slug' => 'docs/03-advanced']);
        Entry::factory()->create(['type' => 'doc', 'slug' => 'docs/01-introduction']);
        Entry::factory()->create(['type' => 'doc', 'slug' => 'docs/02-basics']);

        $entries = Entry::where('type', 'doc')
            ->where('slug', 'like', 'docs/%')
            ->get();

        $this->assertEquals(
            ['docs/01-introduction', 'docs/02-basics', 'docs/03-advanced'],
            $entries->pluck('slug')->toArray()
        );
    }

    public function test_published_scope()
    {
        Entry::factory()->create([
            'type' => 'doc',
            'slug' => 'published',
            'published_at' => now()->subDay(),
        ]);

        Entry::factory()->create([
            'type' => 'doc',
            'slug' => 'scheduled',
            'published_at' => now()->addDay(),
        ]);

        Entry::factory()->create([
            'type' => 'doc',
            'slug' => 'draft',
            'published_at' => null,
        ]);

        $publishedEntries = Entry::published()->get();
        $this->assertCount(1, $publishedEntries);
        $this->assertEquals('published', $publishedEntries->first()->slug);
    }

    public function test_type_scope()
    {
        Entry::factory()->create(['type' => 'doc', 'slug' => 'doc-1']);
        Entry::factory()->create(['type' => 'doc', 'slug' => 'doc-2']);
        Entry::factory()->create(['type' => 'post', 'slug' => 'post-1']);

        $docEntries = Entry::ofType('doc')->get();
        $this->assertCount(2, $docEntries);
        $this->assertEquals(['doc-1', 'doc-2'], $docEntries->pluck('slug')->sort()->values()->toArray());
    }
}
