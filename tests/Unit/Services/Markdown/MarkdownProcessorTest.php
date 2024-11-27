<?php

namespace Tests\Unit\Services\Markdown;

use App\Models\Entry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Tests\Traits\CreatesTestFiles;

class MarkdownProcessorTest extends TestCase
{
    use CreatesTestFiles, RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupTestFiles();
        $this->createMarkdownModelTestFiles();
    }

    public function test_basic_markdown_parsing()
    {
        $model = Entry::createFromMarkdown($this->disk, 'test-basic.md', 'post');

        $this->assertEquals('Test Basic Markdown', $model->title);
        $this->assertEquals('test-basic', $model->slug);
        $this->assertEquals(['tag1', 'tag2'], $model->tags->pluck('name')->toArray());
        $this->assertNotNull($model->published_at);
        $this->assertEquals('2024-01-01 00:00:00', $model->published_at->format('Y-m-d H:i:s'));
        $this->assertEquals('Test description', $model->meta['description']);
        $this->assertEquals('John Doe', $model->meta['author']);
        $this->assertEquals('Test content here', trim($model->content));
        $this->assertFalse($model->is_index);
    }

    public function test_featured_image_handling()
    {
        $model = Entry::createFromMarkdown($this->disk, 'test-basic.md', 'post');
        $featuredImage = $model->images()->where('collection', 'featured')->first();

        $this->assertNotNull($featuredImage);
        $this->assertEquals('featured.jpg', $featuredImage->filename);
        $this->assertEquals(1200, $featuredImage->dimensions['width']);
        $this->assertEquals(630, $featuredImage->dimensions['height']);
        $this->assertEquals('image/jpeg', $featuredImage->mime_type);
        $this->assertNotNull($featuredImage->thumbhash);
    }

    public function test_multiple_image_collections()
    {
        $model = Entry::createFromMarkdown($this->disk, 'test-multiple-images.md', 'post');

        // Verify total counts per collection
        $this->assertEquals(1, $model->images()->where('collection', 'featured')->count());
        $this->assertEquals(2, $model->images()->where('collection', 'gallery')->count());
        $this->assertEquals(2, $model->images()->where('collection', 'thumbnails')->count());

        // Verify featured image dimensions
        $featuredImage = $model->images()->where('collection', 'featured')->first();
        $this->assertEquals(1200, $featuredImage->dimensions['width']);
        $this->assertEquals(630, $featuredImage->dimensions['height']);

        // Verify gallery image dimensions
        $galleryImages = $model->images()->where('collection', 'gallery')->get();
        foreach ($galleryImages as $image) {
            $this->assertEquals(800, $image->dimensions['width']);
            $this->assertEquals(600, $image->dimensions['height']);
        }

        // Verify thumbnail dimensions
        $thumbnailImages = $model->images()->where('collection', 'thumbnails')->get();
        foreach ($thumbnailImages as $image) {
            $this->assertEquals(150, $image->dimensions['width']);
            $this->assertEquals(150, $image->dimensions['height']);
        }
    }

    public function test_inline_image_processing()
    {
        $model = Entry::createFromMarkdown($this->disk, 'images/inline-images.md', 'post');

        $contentImages = $model->images()->where('collection', 'content')->get();
        $this->assertCount(3, $contentImages);

        // Verify transformed content contains responsive image components
        $this->assertStringContainsString('<ResponsiveImage', $model->content);
        $this->assertStringContainsString('alt={"First Image"}', $model->content);
        $this->assertStringContainsString('alt={"Second Image"}', $model->content);
        $this->assertStringContainsString('alt={"Third Image"}', $model->content);
    }

    public function test_mixed_image_references()
    {
        $model = Entry::createFromMarkdown($this->disk, 'images/mixed-references.md', 'post');

        // Verify front matter images
        $this->assertEquals(1, $model->images()->where('collection', 'featured')->count());
        $this->assertEquals(2, $model->images()->where('collection', 'gallery')->count());

        // Verify content images (should deduplicate with front matter)
        $contentImages = $model->images()->where('collection', 'content')->get();
        $this->assertCount(2, $contentImages); // Featured and gallery images reused

        // Verify external image reference remains unchanged
        $this->assertStringContainsString('![External](https://example.com/image.jpg)', $model->content);
    }

    public function test_relative_path_handling()
    {
        $model = Entry::createFromMarkdown($this->disk, 'images/nested/relative-paths.md', 'post');
        $contentImages = $model->images()->where('collection', 'content')->get();

        // Should resolve three images: ../square.jpg, ./external.jpg, and /images/landscape.jpg
        $this->assertCount(3, $contentImages);

        $filenames = $contentImages->pluck('filename')->toArray();
        $this->assertContains('square.jpg', $filenames);
        $this->assertContains('external.jpg', $filenames);
        $this->assertContains('landscape.jpg', $filenames);
    }

    public function test_index_file_handling()
    {
        // Test root index
        $rootIndex = Entry::createFromMarkdown($this->disk, 'index.md', 'doc');
        $this->assertEquals('', $rootIndex->slug);
        $this->assertEquals('Root Index', $rootIndex->title);

        // Test section index
        $sectionIndex = Entry::createFromMarkdown($this->disk, 'section/index.md', 'doc');
        $this->assertEquals('section', $sectionIndex->slug);
        $this->assertEquals('Section Index', $sectionIndex->title);
    }

    public function test_hierarchical_documentation()
    {
        // Create root docs index
        $docsIndex = Entry::createFromMarkdown($this->disk, 'docs/index.md', 'doc');
        $this->assertEquals('docs', $docsIndex->slug);
        $this->assertTrue($docsIndex->is_index);
        $this->assertEquals('Documentation', $docsIndex->title);
        $this->assertEquals(1, $docsIndex->meta['nav_order']);

        // Create section index
        $gettingStarted = Entry::createFromMarkdown($this->disk, 'docs/getting-started/index.md', 'doc');
        $this->assertEquals('docs/getting-started', $gettingStarted->slug);
        $this->assertTrue($gettingStarted->is_index);
        $this->assertEquals('Getting Started', $gettingStarted->title);
        $this->assertEquals(2, $gettingStarted->meta['nav_order']);

        // Create content file
        $installation = Entry::createFromMarkdown($this->disk, 'docs/getting-started/installation.md', 'doc');
        $this->assertEquals('docs/getting-started/installation', $installation->slug);
        $this->assertFalse($installation->is_index);
        $this->assertEquals('Installation', $installation->title);
        $this->assertEquals('beginner', $installation->meta['difficulty']);
        $this->assertEquals(['git', 'php'], $installation->meta['prerequisites']);
    }

    public function test_content_syncing()
    {
        // Initial sync
        $model = Entry::syncFromMarkdown($this->disk, 'test-sync.md', 'post', true);
        $initialId = $model->id;

        $this->assertEquals('Initial Title', $model->title);
        $this->assertEquals('test-sync', $model->slug);
        $this->assertEquals('1.0.0', $model->meta['version']);

        // Update content
        $this->disk->delete('test-sync.md');
        $this->disk->copy('test-sync-updated.md', 'test-sync.md');

        // Sync updated content
        $updated = Entry::syncFromMarkdown($this->disk, 'test-sync.md', 'post', true);

        $this->assertEquals($initialId, $updated->id);
        $this->assertEquals('Updated Title', $updated->title);
        $this->assertEquals('1.0.1', $updated->meta['version']);
        $this->assertEquals(1, Entry::count());
    }

    public function test_published_at_handling()
    {
        // Test explicit published date
        $dated = Entry::createFromMarkdown($this->disk, 'dates/iso-format.md', 'post');
        $this->assertEquals('2024-01-01 10:00:00', $dated->published_at->format('Y-m-d H:i:s'));

        // Test published: true
        $publishedTrue = Entry::createFromMarkdown($this->disk, 'special/published-true.md', 'post');
        $this->assertNotNull($publishedTrue->published_at);
        $this->assertEqualsWithDelta(now(), $publishedTrue->published_at, 1);

        // Test date preservation during sync
        $originalDate = now()->subDays(5);
        $publishedTrue->published_at = $originalDate;
        $publishedTrue->save();

        $synced = Entry::syncFromMarkdown($this->disk, 'special/published-true.md', 'post', true);
        $this->assertEquals($originalDate->timestamp, $synced->published_at->timestamp);
    }

    public function test_special_cases()
    {
        // Test no front matter title
        $noTitle = Entry::createFromMarkdown($this->disk, 'special/no-title.md', 'post');
        $this->assertEquals('Markdown Title', $noTitle->title);

        // Test multiple headings
        $multipleHeadings = Entry::createFromMarkdown($this->disk, 'special/multiple-headings.md', 'post');
        $this->assertEquals('Main Title', $multipleHeadings->title);
        $this->assertStringContainsString('## Subtitle', $multipleHeadings->content);
    }

    public function test_complex_metadata()
    {
        // Test special characters in meta
        $specialChars = Entry::createFromMarkdown($this->disk, 'meta/special-chars.md', 'post');
        $this->assertEquals('String with "quotes"', $specialChars->meta['quotes']);
        $this->assertEquals("Line 1\nLine 2", $specialChars->meta['multiline']);
        $this->assertEquals('$@#%', $specialChars->meta['symbols']);

        // Test deeply nested meta
        $complexMeta = Entry::createFromMarkdown($this->disk, 'meta/complex-meta.md', 'post');
        $this->assertEquals('deep value', $complexMeta->meta['level1']['level2']['level3']);
        $this->assertEquals([1, 2, 3], $complexMeta->meta['level1']['array']);
    }

    public function test_internal_markdown_links()
    {
        $content = $this->disk->put('docs/getting-started/installation.md', <<<'MD'
# Installation

For setup, see the [Configuration Guide](configuration.md).
Check the [Prerequisites](../prerequisites.md) first.
See our [Advanced Guide](../advanced/guide.md) for more.
Back to [Getting Started](./index.md) or [Home](../index.md).
The [Overview](./overview.md) explains more.
Also see [API Reference](../reference/index.md).

Some edge cases:
- [Multiple Levels Up](../../index.md)
- [Deep Nested Index](../advanced/topics/index.md)
- [Mixed Slashes](./path/./to/../current.md)
- [Link With Spaces](./path/my page.md)
- [Link With Anchor](configuration.md#setup)
- [Link With Query](configuration.md?version=2)
- [Double Extension](configuration.md.backup.md)

External links should be unchanged:
- [GitHub](https://github.com)
- [HTTPS Link](https://example.com/docs/guide.md)
- [HTTP Link](http://example.com/guide.md)
- [FTP Link](ftp://example.com/guide.md)
MD
        );

        $model = Entry::createFromMarkdown($this->disk, 'docs/getting-started/installation.md', 'doc');

        // Current directory links
        $this->assertStringContainsString('[Configuration Guide](configuration)', $model->content);
        $this->assertStringContainsString('[Overview](overview)', $model->content);

        // Index file links
        $this->assertStringContainsString('[Getting Started](.)', $model->content);  // ./index.md -> .
        $this->assertStringContainsString('[Home](..)', $model->content);  // ../index.md -> ..
        $this->assertStringContainsString('[API Reference](../reference)', $model->content);  // index.md removal

        // Parent directory links
        $this->assertStringContainsString('[Prerequisites](../prerequisites)', $model->content);
        $this->assertStringContainsString('[Advanced Guide](../advanced/guide)', $model->content);

        // Edge cases
        $this->assertStringContainsString('[Multiple Levels Up](../..)', $model->content);
        $this->assertStringContainsString('[Deep Nested Index](../advanced/topics)', $model->content);
        $this->assertStringContainsString('[Mixed Slashes](path/current)', $model->content);
        $this->assertStringContainsString('[Link With Spaces](path/my page)', $model->content);
        $this->assertStringContainsString('[Link With Anchor](configuration#setup)', $model->content);
        $this->assertStringContainsString('[Link With Query](configuration?version=2)', $model->content);
        $this->assertStringContainsString('[Double Extension](configuration.md.backup)', $model->content);

        // External links should be unchanged
        $this->assertStringContainsString('[GitHub](https://github.com)', $model->content);
        $this->assertStringContainsString('[HTTPS Link](https://example.com/docs/guide.md)', $model->content);
        $this->assertStringContainsString('[HTTP Link](http://example.com/guide.md)', $model->content);
        $this->assertStringContainsString('[FTP Link](ftp://example.com/guide.md)', $model->content);
    }

    public function test_invalid_content_handling()
    {
        $this->expectException(\Exception::class);
        Entry::createFromMarkdown($this->disk, 'invalid/bad-yaml.md', 'post');
    }

    protected function tearDown(): void
    {
        $this->tearDownTestFiles();
        parent::tearDown();
    }
}
