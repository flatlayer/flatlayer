<?php

namespace Tests\Unit;

use App\Models\Entry;
use App\Traits\HasNavigation;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationTraitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupChronologicalEntries();
        $this->setupHierarchicalEntries();
    }

    protected function setupChronologicalEntries(): void
    {
        // Create a series of published entries with different dates
        Entry::factory()->create([
            'type' => 'post',
            'title' => 'First Post',
            'published_at' => Carbon::parse('2024-01-01'),
        ]);

        Entry::factory()->create([
            'type' => 'post',
            'title' => 'Second Post',
            'published_at' => Carbon::parse('2024-01-15'),
        ]);

        Entry::factory()->create([
            'type' => 'post',
            'title' => 'Third Post',
            'published_at' => Carbon::parse('2024-02-01'),
        ]);

        // Create an unpublished post
        Entry::factory()->create([
            'type' => 'post',
            'title' => 'Draft Post',
            'published_at' => null,
        ]);

        // Create a post of different type
        Entry::factory()->create([
            'type' => 'page',
            'title' => 'Different Type',
            'published_at' => Carbon::parse('2024-01-10'),
        ]);
    }

    protected function setupHierarchicalEntries(): void
    {
        // Create root level entries
        Entry::factory()->create([
            'type' => 'doc',
            'title' => 'Documentation',
            'slug' => 'docs',
            'meta' => ['nav_order' => 1],
        ]);

        // Create first section with nested content
        Entry::factory()->create([
            'type' => 'doc',
            'title' => 'Getting Started',
            'slug' => 'docs/getting-started',
            'meta' => ['nav_order' => 2],
        ]);

        Entry::factory()->create([
            'type' => 'doc',
            'title' => 'Installation',
            'slug' => 'docs/getting-started/installation',
            'meta' => ['nav_order' => 1],
        ]);

        Entry::factory()->create([
            'type' => 'doc',
            'title' => 'Configuration',
            'slug' => 'docs/getting-started/configuration',
            'meta' => ['nav_order' => 2],
        ]);

        // Create second section with nested content
        Entry::factory()->create([
            'type' => 'doc',
            'title' => 'Advanced Topics',
            'slug' => 'docs/advanced',
            'meta' => ['nav_order' => 3],
        ]);

        Entry::factory()->create([
            'type' => 'doc',
            'title' => 'Security',
            'slug' => 'docs/advanced/security',
            'meta' => ['nav_order' => 1],
        ]);

        Entry::factory()->create([
            'type' => 'doc',
            'title' => 'Performance',
            'slug' => 'docs/advanced/performance',
            'meta' => ['nav_order' => 2],
        ]);
    }

    public function test_chronological_navigation()
    {
        $middlePost = Entry::where('title', 'Second Post')->first();
        $navigation = $middlePost->getNavigation('chronological');

        $this->assertEquals('First Post', $navigation['previous']->title);
        $this->assertEquals('Third Post', $navigation['next']->title);
        $this->assertEquals(2, $navigation['position']['current']);
        $this->assertEquals(3, $navigation['position']['total']);
    }

    public function test_chronological_navigation_for_first_post()
    {
        $firstPost = Entry::where('title', 'First Post')->first();
        $navigation = $firstPost->getNavigation('chronological');

        $this->assertNull($navigation['previous']);
        $this->assertEquals('Second Post', $navigation['next']->title);
        $this->assertEquals(1, $navigation['position']['current']);
        $this->assertEquals(3, $navigation['position']['total']);
    }

    public function test_chronological_navigation_for_last_post()
    {
        $lastPost = Entry::where('title', 'Third Post')->first();
        $navigation = $lastPost->getNavigation('chronological');

        $this->assertEquals('Second Post', $navigation['previous']->title);
        $this->assertNull($navigation['next']);
        $this->assertEquals(3, $navigation['position']['current']);
        $this->assertEquals(3, $navigation['position']['total']);
    }

    public function test_chronological_navigation_respects_published_status()
    {
        $draftPost = Entry::where('title', 'Draft Post')->first();
        $navigation = $draftPost->getNavigation('chronological');

        // For unpublished posts, all navigation elements should be null
        $this->assertNull($navigation['previous']);
        $this->assertNull($navigation['next']);
        $this->assertNull($navigation['position']);
    }

    public function test_hierarchical_navigation_for_middle_item()
    {
        $installation = Entry::where('title', 'Installation')->first();
        $navigation = $installation->getNavigation('hierarchical');

        $this->assertNotNull($navigation['previous']);
        $this->assertNotNull($navigation['next']);
        $this->assertEquals('Getting Started', $navigation['previous']->title);
        $this->assertEquals('Configuration', $navigation['next']->title);
        $this->assertEquals(1, $navigation['position']['current']); // Installation is first of its siblings
        $this->assertEquals(2, $navigation['position']['total']); // Installation and Configuration are siblings
    }

    public function test_chronological_navigation_excludes_unpublished_posts()
    {
        // Get the second post
        $secondPost = Entry::where('title', 'Second Post')->first();
        $navigation = $secondPost->getNavigation('chronological');

        // Navigation should skip unpublished posts
        $this->assertEquals('First Post', $navigation['previous']->title);
        $this->assertEquals('Third Post', $navigation['next']->title);
    }

    public function test_hierarchical_navigation_respects_nav_order()
    {
        $security = Entry::where('title', 'Security')->first();
        $navigation = $security->getNavigation('hierarchical');

        $this->assertEquals('Advanced Topics', $navigation['previous']->title);
        $this->assertEquals('Performance', $navigation['next']->title);
    }

    public function test_hierarchical_navigation_across_sections()
    {
        $lastItemFirstSection = Entry::where('title', 'Configuration')->first();
        $navigation = $lastItemFirstSection->getNavigation('hierarchical');

        $this->assertEquals('Installation', $navigation['previous']->title);
        $this->assertEquals('Advanced Topics', $navigation['next']->title);
    }

    public function test_hierarchical_navigation_for_first_item()
    {
        $docs = Entry::where('title', 'Documentation')->first();
        $navigation = $docs->getNavigation('hierarchical');

        $this->assertNull($navigation['previous']);
        $this->assertEquals('Getting Started', $navigation['next']->title);
        $this->assertEquals(1, $navigation['position']['current']);
    }

    public function test_hierarchical_navigation_for_last_item()
    {
        $performance = Entry::where('title', 'Performance')->first();
        $navigation = $performance->getNavigation('hierarchical');

        $this->assertEquals('Security', $navigation['previous']->title);
        $this->assertNull($navigation['next']);
    }

    public function test_invalid_navigation_type_throws_exception()
    {
        $entry = Entry::first();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid navigation type: invalid');

        $entry->getNavigation('invalid');
    }

    public function test_navigation_with_missing_nav_order()
    {
        // Create entries without nav_order, should fall back to title sorting
        Entry::factory()->create([
            'type' => 'doc',
            'title' => 'B Page',
            'slug' => 'docs/page-b',
            'meta' => [],
        ]);

        Entry::factory()->create([
            'type' => 'doc',
            'title' => 'A Page',
            'slug' => 'docs/page-a',
            'meta' => [],
        ]);

        $pageB = Entry::where('title', 'B Page')->first();
        $navigation = $pageB->getNavigation('hierarchical');

        $this->assertEquals('A Page', $navigation['previous']->title);
    }

    public function test_hierarchical_navigation_with_mixed_nav_order()
    {
        // Create a mix of entries with and without nav_order
        Entry::factory()->create([
            'type' => 'doc',
            'title' => 'Z Page',
            'slug' => 'docs/section/z-page',
            'meta' => ['nav_order' => 1],
        ]);

        Entry::factory()->create([
            'type' => 'doc',
            'title' => 'A Page',
            'slug' => 'docs/section/a-page',
            'meta' => [],  // No nav_order
        ]);

        $zPage = Entry::where('title', 'Z Page')->first();
        $navigation = $zPage->getNavigation('hierarchical');

        // Z Page should come first due to nav_order, despite alphabetical order
        $this->assertEquals('A Page', $navigation['next']->title);
    }
}
