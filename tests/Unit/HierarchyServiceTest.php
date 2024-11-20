<?php

namespace Tests\Unit;

use App\Models\Entry;
use App\Services\Content\ContentHierarchy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HierarchyServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ContentHierarchy $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ContentHierarchy;
        $this->createTestHierarchy();
    }

    protected function createTestHierarchy(): void
    {
        // Create root level entries
        Entry::factory()->create([
            'type' => 'docs',
            'title' => 'Documentation',
            'slug' => 'docs',
            'meta' => ['section' => 'root', 'nav_order' => 1],
        ]);

        // Create nested documentation structure
        Entry::factory()->create([
            'type' => 'docs',
            'title' => 'Getting Started',
            'slug' => 'docs/getting-started',
            'meta' => ['section' => 'guide', 'nav_order' => 2],
        ]);

        Entry::factory()->create([
            'type' => 'docs',
            'title' => 'Installation',
            'slug' => 'docs/getting-started/installation',
            'meta' => ['section' => 'guide', 'nav_order' => 1],
        ]);

        Entry::factory()->create([
            'type' => 'docs',
            'title' => 'Configuration',
            'slug' => 'docs/getting-started/configuration',
            'meta' => ['section' => 'guide', 'nav_order' => 2],
        ]);

        // Create another section
        Entry::factory()->create([
            'type' => 'docs',
            'title' => 'Advanced Topics',
            'slug' => 'docs/advanced',
            'meta' => ['section' => 'advanced', 'nav_order' => 3],
        ]);

        Entry::factory()->create([
            'type' => 'docs',
            'title' => 'Security',
            'slug' => 'docs/advanced/security',
            'meta' => ['section' => 'advanced', 'nav_order' => 1],
        ]);

        // Create some blog posts (different type)
        Entry::factory()->create([
            'type' => 'blog',
            'title' => 'First Post',
            'slug' => 'first-post',
        ]);

        Entry::factory()->create([
            'type' => 'blog',
            'title' => 'Second Post',
            'slug' => 'second-post',
        ]);
    }

    public function test_builds_complete_hierarchy()
    {
        $hierarchy = $this->service->buildHierarchy('docs');

        $this->assertCount(1, $hierarchy); // just 'docs'
        $this->assertEquals('Documentation', $hierarchy[0]['title']);

        // Check both children are present and ordered by nav_order
        $children = $hierarchy[0]['children'];
        $this->assertCount(2, $children);
        $this->assertEquals('Advanced Topics', $children[0]['title']);
        $this->assertEquals('Getting Started', $children[1]['title']);

        // Check their children
        $this->assertCount(1, $children[0]['children']); // Advanced Topics has Security
        $this->assertCount(2, $children[1]['children']); // Getting Started has Installation and Configuration
    }

    public function test_builds_partial_hierarchy_from_root()
    {
        $hierarchy = $this->service->buildHierarchy('docs', 'docs/getting-started');

        $this->assertCount(1, $hierarchy);
        $this->assertEquals('Getting Started', $hierarchy[0]['title']);
        $this->assertCount(2, $hierarchy[0]['children']);
    }

    public function test_respects_depth_limit()
    {
        $hierarchy = $this->service->buildHierarchy('docs', null, ['depth' => 1]);

        $this->assertCount(1, $hierarchy);
        $this->assertEquals([], $hierarchy[0]['children']);
    }

    public function test_applies_field_filtering()
    {
        $hierarchy = $this->service->buildHierarchy('docs', null, [
            'fields' => ['title', 'slug'],
        ]);

        $this->assertArrayHasKey('title', $hierarchy[0]);
        $this->assertArrayHasKey('slug', $hierarchy[0]);
        $this->assertArrayNotHasKey('id', $hierarchy[0]);
        $this->assertArrayNotHasKey('meta', $hierarchy[0]);
    }

    public function test_sorts_nodes_by_metadata()
    {
        $hierarchy = $this->service->buildHierarchy('docs', null, [
            'sort' => ['meta.nav_order' => 'asc'],
        ]);

        $children = $hierarchy[0]['children'];
        $this->assertEquals('Getting Started', $children[0]['title']);
        $this->assertEquals('Advanced Topics', $children[1]['title']);

        // Also verify the grandchildren are sorted
        $gettingStartedChildren = $children[0]['children'];
        $this->assertEquals('Installation', $gettingStartedChildren[0]['title']);
        $this->assertEquals('Configuration', $gettingStartedChildren[1]['title']);
    }

    public function test_finds_specific_node()
    {
        $hierarchy = $this->service->buildHierarchy('docs');
        $node = $this->service->findNode($hierarchy, 'docs/getting-started/installation');

        $this->assertNotNull($node);
        $this->assertEquals('Installation', $node['title']);
    }

    public function test_returns_null_for_nonexistent_node()
    {
        $hierarchy = $this->service->buildHierarchy('docs');
        $node = $this->service->findNode($hierarchy, 'docs/nonexistent');

        $this->assertNull($node);
    }

    public function test_gets_node_ancestry()
    {
        $hierarchy = $this->service->buildHierarchy('docs');
        $ancestry = $this->service->getAncestry($hierarchy, 'docs/getting-started/installation');

        $this->assertCount(2, $ancestry);
        $this->assertEquals('Documentation', $ancestry[0]['title']);
        $this->assertEquals('Getting Started', $ancestry[1]['title']);
    }

    public function test_flattens_hierarchy_to_paths()
    {
        $hierarchy = $this->service->buildHierarchy('docs');
        $paths = $this->service->flattenHierarchy($hierarchy);

        $this->assertContains('docs', $paths);
        $this->assertContains('docs/getting-started', $paths);
        $this->assertContains('docs/getting-started/installation', $paths);
        $this->assertContains('docs/getting-started/configuration', $paths);
    }

    public function test_handles_missing_content_type()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->buildHierarchy('nonexistent');
    }

    public function test_handles_invalid_root_path()
    {
        $hierarchy = $this->service->buildHierarchy('docs', 'invalid/path');
        $this->assertEmpty($hierarchy);
    }

    public function test_filters_by_type()
    {
        $blogHierarchy = $this->service->buildHierarchy('blog');
        $this->assertCount(2, $blogHierarchy);
        $this->assertEquals('First Post', $blogHierarchy[0]['title']);
    }

    public function test_handles_meta_field_selection()
    {
        $hierarchy = $this->service->buildHierarchy('docs', null, [
            'fields' => ['title', 'meta.section'],
        ]);

        $this->assertArrayHasKey('meta', $hierarchy[0]);
        $this->assertEquals('root', $hierarchy[0]['meta']['section']);
    }

    public function test_respects_index_files()
    {
        // Create an index file structure
        Entry::factory()->create([
            'type' => 'docs',
            'title' => 'Index Page',
            'slug' => 'docs/section',
            'filename' => 'docs/section/index.md',
        ]);

        Entry::factory()->create([
            'type' => 'docs',
            'title' => 'Child Page',
            'slug' => 'docs/section/page',
            'filename' => 'docs/section/page.md',
        ]);

        $hierarchy = $this->service->buildHierarchy('docs', 'docs/section');

        $this->assertCount(1, $hierarchy);
        $this->assertEquals('Index Page', $hierarchy[0]['title']);
        $this->assertCount(1, $hierarchy[0]['children']);
        $this->assertEquals('Child Page', $hierarchy[0]['children'][0]['title']);
    }
}
