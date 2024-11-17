<?php

namespace Tests\Unit;

use App\Models\Entry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\CreatesTestFiles;

class MarkdownModelTest extends TestCase
{
    use RefreshDatabase, WithFaker, CreatesTestFiles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupTestFiles();
    }

    public function test_create_entry_from_markdown_with_front_matter_images()
    {
        // Create the image with relative paths first
        $images = $this->createImageSet('images', [
            'featured' => [
                'width' => 1200,
                'height' => 630,
                'extension' => 'jpg',
                'background' => '#ff0000'
            ]
        ]);

        // Create post with relative path to image
        $path = $this->createCompletePost(
            'test-basic.md',
            'Test Basic Markdown',
            ['tag1', 'tag2'],
            now()->subDay(),
            ['description' => 'Test description', 'keywords' => ['test', 'markdown']],
            ['author' => 'John Doe'],
            ['featured' => 'images/featured.jpg']
        );

        // Create the entry using the static factory method
        $model = Entry::createFromMarkdown($path, 'post');

        // Verify basic attributes
        $this->assertEquals('Test Basic Markdown', $model->title);
        $this->assertStringContainsString('Test Basic Markdown', $model->title);
        $this->assertEquals('test-basic', $model->slug);
        $this->assertEquals(['tag1', 'tag2'], $model->tags->pluck('name')->toArray());

        // Verify meta information
        $this->assertStringContainsString('Test description', $model->meta['seo']['description']);
        $this->assertEquals('John Doe', $model->meta['author']);
        $this->assertNotNull($model->published_at);

        // Verify images
        $this->assertEquals(1, $model->images()->where('collection', 'featured')->count());

        // Additional image verification
        $featuredImage = $model->images()->where('collection', 'featured')->first();
        $this->assertEquals(1200, $featuredImage->dimensions['width']);
        $this->assertEquals(630, $featuredImage->dimensions['height']);
        $this->assertEquals('featured.jpg', $featuredImage->filename);
    }

    public function test_sync_entry_from_markdown()
    {
        // Create initial file
        $path = $this->createMarkdownFile(
            'test-sync.md',
            [
                'title' => 'Initial Title',
                'type' => 'post',
                'meta' => ['version' => '1.0.0']
            ],
            'Initial content'
        );

        // Initial sync
        $model = Entry::syncFromMarkdown($path, 'post', true);

        $initialId = $model->id;
        $this->assertEquals('Initial Title', $model->title);
        $this->assertEquals('1.0.0', $model->meta['version']);

        // Replace the file with updated content
        Storage::delete($this->getRelativePath($path));
        $updatedPath = $this->createMarkdownFile(
            'test-sync.md', // Same filename
            [
                'title' => 'Updated Title',
                'type' => 'post',
                'meta' => ['version' => '1.0.1']
            ],
            'Updated content'
        );

        // Sync updated version
        $updatedModel = Entry::syncFromMarkdown($updatedPath, 'post', true);

        $this->assertEquals($initialId, $updatedModel->id);
        $this->assertEquals('Updated Title', $updatedModel->title);
        $this->assertEquals('1.0.1', $updatedModel->meta['version']);
        $this->assertEquals(1, Entry::count());
    }

    public function test_process_markdown_with_embedded_images()
    {
        // Create specification for test images
        $imageSpecs = [
            'image1' => ['width' => 800, 'height' => 600, 'extension' => 'jpg'],
            'image2' => ['width' => 400, 'height' => 300, 'extension' => 'png'],
        ];

        // Create post with embedded images using our specs
        $path = $this->createPostWithEmbeddedImages(
            'test-embedded.md',
            'Test Embedded Images',
            $imageSpecs, // Pass in our custom specs
            true // Include an external image reference
        );

        // Create the entry using the static factory method
        $model = Entry::createFromMarkdown($path, 'post');

        // Verify responsive image components
        $this->assertStringContainsString('<ResponsiveImage', $model->content);
        $this->assertStringContainsString('imageData=', $model->content);
        $this->assertStringContainsString('alt={"image1"}', $model->content);
        $this->assertStringContainsString('alt={"image2"}', $model->content);
        $this->assertStringContainsString('![External Image](https://example.com/image.jpg)', $model->content);

        // Verify database records
        $this->assertEquals(2, $model->images()->where('collection', 'content')->count());

        // Verify image dimensions
        $contentImages = $model->images()->where('collection', 'content')->get();
        $expectedDimensions = [
            'image1' => ['width' => 800, 'height' => 600],
            'image2' => ['width' => 400, 'height' => 300]
        ];

        foreach ($contentImages as $image) {
            $filename = pathinfo($image->filename, PATHINFO_FILENAME);
            $this->assertEquals($expectedDimensions[$filename]['width'], $image->dimensions['width']);
            $this->assertEquals($expectedDimensions[$filename]['height'], $image->dimensions['height']);
        }
    }

    public function test_handle_special_published_states()
    {
        // Test immediate publication
        $publishedPath = $this->createSpecialCaseFile('published-true');
        $model = Entry::createFromMarkdown($publishedPath, 'post');

        $this->assertNotNull($model->published_at);
        $this->assertEqualsWithDelta(now(), $model->published_at, 1);

        // Test preservation of existing publication date
        $originalDate = now()->subDays(5);
        $model->published_at = $originalDate;
        $model->save();

        $updatedModel = Entry::syncFromMarkdown($publishedPath, 'post', true);

        $this->assertEquals($originalDate->timestamp, $updatedModel->published_at->timestamp);
    }

    public function test_handle_complex_metadata()
    {
        // Create hierarchical content structure
        $structure = [
            'docs' => [
                'index.md' => [
                    'title' => 'Documentation',
                    'meta' => ['section' => 'root', 'nav_order' => 1]
                ],
                'getting-started' => [
                    'index.md' => [
                        'title' => 'Getting Started',
                        'meta' => ['section' => 'tutorial', 'nav_order' => 2]
                    ],
                    'installation.md' => [
                        'title' => 'Installation',
                        'meta' => [
                            'difficulty' => 'beginner',
                            'time_required' => 15,
                            'prerequisites' => ['git', 'php']
                        ]
                    ]
                ]
            ]
        ];

        $files = $this->createHierarchicalContent($structure);

        foreach ($files as $path) {
            $slug = Str::beforeLast(Str::after($path, $this->testContentPath . '/'), '.md');
            $model = Entry::createFromMarkdown($path, 'doc');

            $this->assertNotEmpty($model->meta);
            if (str_contains($path, 'installation.md')) {
                $this->assertEquals('beginner', $model->meta['difficulty']);
                $this->assertEquals(['git', 'php'], $model->meta['prerequisites']);
            }
        }
    }

    public function test_handle_multiple_image_collections()
    {
        $imageSpecs = [
            'featured' => ['width' => 1200, 'height' => 630, 'extension' => 'jpg'],
            'gallery1' => ['width' => 800, 'height' => 600, 'extension' => 'jpg'],
            'gallery2' => ['width' => 800, 'height' => 600, 'extension' => 'jpg'],
            'thumb1' => ['width' => 150, 'height' => 150, 'extension' => 'png'],
            'thumb2' => ['width' => 150, 'height' => 150, 'extension' => 'png']
        ];

        $images = $this->createImageSet($this->getTestPath('images'), $imageSpecs);

        $path = $this->createMarkdownFile(
            'test-multiple-images.md',
            [
                'type' => 'post',
                'title' => 'Test Multiple Images',
                'images' => [
                    'featured' => $images['featured'],
                    'gallery' => [$images['gallery1'], $images['gallery2']],
                    'thumbnails' => [$images['thumb1'], $images['thumb2']]
                ]
            ],
            "# Test Multiple Images\n\nContent with multiple image collections"
        );

        $model = Entry::createFromMarkdown($path, 'post');

        $this->assertEquals(5, $model->images()->count());
        $this->assertEquals(1, $model->images()->where('collection', 'featured')->count());
        $this->assertEquals(2, $model->images()->where('collection', 'gallery')->count());
        $this->assertEquals(2, $model->images()->where('collection', 'thumbnails')->count());

        // Verify image dimensions
        $this->assertEquals(
            ['width' => 1200, 'height' => 630],
            $model->images()->where('collection', 'featured')->first()->dimensions
        );

        // Verify gallery dimensions
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

    protected function tearDown(): void
    {
        $this->tearDownTestFiles();
        parent::tearDown();
    }
}
