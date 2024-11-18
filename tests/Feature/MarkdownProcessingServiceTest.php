<?php

namespace Tests\Feature;

use App\Models\Entry;
use App\Services\ImageService;
use App\Services\MarkdownProcessingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use InvalidArgumentException;
use Tests\TestCase;
use Tests\Traits\CreatesTestFiles;

class MarkdownProcessingServiceTest extends TestCase
{
    use CreatesTestFiles, RefreshDatabase;

    protected MarkdownProcessingService $service;

    protected ImageService $imageService;

    protected Entry $entry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupTestFiles();

        $this->imageService = new ImageService(
            disk: $this->disk,
            imageManager: new ImageManager(new Driver)
        );

        $this->service = new MarkdownProcessingService(
            imageService: $this->imageService,
            disk: $this->disk
        );

        $this->entry = Entry::factory()->create(['type' => 'post']);

        // Create all test files using the trait
        $this->createMarkdownModelTestFiles();
    }

    public function test_process_markdown_file_with_frontmatter()
    {
        $result = $this->service->processMarkdownFile(
            'test-basic.md',
            'post',
            'test-basic',
            ['title', 'type', 'tags', 'published_at'],
            ['dateFormat' => 'Y-m-d']
        );

        $this->assertEquals('Test Basic Markdown', $result['title']);
        $this->assertEquals('post', $result['type']);
        $this->assertEquals(['tag1', 'tag2'], $result['tags']);
        $this->assertEquals('2024-01-01', $result['published_at']);
        $this->assertEquals('Test description', $result['meta']['description']);
        $this->assertEquals('John Doe', $result['meta']['author']);
    }

    public function test_handle_media_from_front_matter()
    {
        $this->service->handleMediaFromFrontMatter(
            $this->entry,
            ['featured' => 'images/featured.jpg'],
            'test-basic.md'
        );

        $this->assertEquals(1, $this->entry->images()->count());

        $this->assertDatabaseHas('images', [
            'entry_id' => $this->entry->id,
            'collection' => 'featured',
            'filename' => 'featured.jpg',
        ]);
    }

    public function test_process_markdown_images()
    {
        $content = $this->disk->get('images/inline-images.md');
        $result = $this->service->processMarkdownImages($this->entry, $content, 'images/inline-images.md');

        $this->assertStringContainsString('<ResponsiveImage', $result);
        $this->assertStringContainsString('alt={"First Image"}', $result);
        $this->assertStringContainsString('alt={"Second Image"}', $result);
        $this->assertEquals(3, $this->entry->images()->where('collection', 'content')->count());
    }

    public function test_handles_relative_image_paths()
    {
        $this->createMarkdownFile('nested/page.md', [
            'type' => 'post',
            'images' => [
                'featured' => '../images/featured.jpg',
            ],
        ], '![Image](../images/featured.jpg)');

        $result = $this->service->processMarkdownFile(
            'nested/page.md',
            'post',
            'nested/page',
            ['type']
        );

        $this->assertArrayHasKey('images', $result);
        $this->assertEquals('../images/featured.jpg', $result['images']['featured']);
    }

    public function test_handles_html_in_markdown()
    {
        $this->createMarkdownFile('mixed.md', [
            'type' => 'post',
        ], "# Title\n<div class=\"custom\">HTML content</div>\n\nMarkdown content");

        $result = $this->service->processMarkdownFile(
            'mixed.md',
            'post',
            'mixed',
            ['title', 'content']
        );

        $this->assertStringContainsString('<div class="custom">HTML content</div>', $result['content']);
    }

    public function test_handles_special_characters_in_filenames()
    {
        $this->createMarkdownFile('special-@#$-chars.md', ['type' => 'post'], 'Content');

        $result = $this->service->processMarkdownFile(
            'special-@#$-chars.md',
            'post',
            'special-chars',
            ['type']
        );

        $this->assertEquals('special-chars', $result['slug']);
    }

    public function test_handles_duplicate_image_references()
    {
        $content = "![Image](images/featured.jpg)\n![Same Image](images/featured.jpg)";
        $this->createMarkdownFile('duplicate-images.md', ['type' => 'post'], $content);

        $result = $this->service->processMarkdownImages($this->entry, $content, 'duplicate-images.md');

        $this->assertEquals(1, $this->entry->images()->where('collection', 'content')->count());
    }

    public function test_processes_hierarchical_content()
    {
        $rootMeta = [
            'type' => 'doc',
            'meta' => ['shared' => 'root-value'],
        ];

        $childMeta = [
            'type' => 'doc',
            'meta' => ['local' => 'child-value'],
        ];

        $this->createMarkdownFile('section/index.md', $rootMeta, 'Root content');
        $this->createMarkdownFile('section/child.md', $childMeta, 'Child content');

        $root = $this->service->processMarkdownFile('section/index.md', 'doc', 'section');
        $child = $this->service->processMarkdownFile('section/child.md', 'doc', 'section/child');

        $this->assertEquals('root-value', $root['meta']['shared']);
        $this->assertEquals('child-value', $child['meta']['local']);
    }

    public function test_handles_malformed_markdown()
    {
        $content = "# Unclosed [link\nText with *unclosed emphasis\n";
        $this->createMarkdownFile('malformed.md', ['type' => 'post'], $content);

        $result = $this->service->processMarkdownFile(
            'malformed.md',
            'post',
            'malformed',
            ['title', 'content']
        );

        $this->assertNotEmpty($result['content']);
    }

    public function test_handles_missing_files()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->processMarkdownFile('missing.md', 'post', 'missing');
    }

    public function test_handles_invalid_front_matter()
    {
        $this->expectException(\Exception::class);
        $this->service->processMarkdownFile('invalid/bad-yaml.md', 'post', 'invalid');
    }

    public function test_date_handling()
    {
        $result = $this->service->processMarkdownFile(
            'special/published-true.md',
            'post',
            'published-true',
            ['published_at']
        );
        $this->assertNotNull($result['published_at']);
    }

    public function test_normalize_tag_values()
    {
        $method = new \ReflectionMethod($this->service, 'normalizeTagValue');

        $this->assertEquals(['tag1', 'tag2'], $method->invoke($this->service, 'tag1,tag2'));
        $this->assertEquals(['tag1', 'tag2'], $method->invoke($this->service, ['tag1', 'tag2']));
        $this->assertEquals([], $method->invoke($this->service, ''));
    }

    public function test_normalize_date_values()
    {
        $method = new \ReflectionMethod($this->service, 'normalizeDateValue');

        $this->assertEquals('2024-01-01 00:00:00', $method->invoke($this->service, '2024-01-01'));
        $this->assertNotNull($method->invoke($this->service, true));
        $this->assertNull($method->invoke($this->service, 'invalid-date'));
    }

    public function test_handles_timestamp_dates()
    {
        // 2024-01-01 00:00:00
        $timestamp = 1704067200;

        $method = new \ReflectionMethod($this->service, 'normalizeDateValue');

        $this->assertEquals(
            '2024-01-01 00:00:00',
            $method->invoke($this->service, $timestamp)
        );
    }

    protected function tearDown(): void
    {
        $this->tearDownTestFiles();
        parent::tearDown();
    }
}
