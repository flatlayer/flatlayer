<?php

namespace Tests\Unit;

use App\Models\Entry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Tests\TestCase;

class MarkdownModelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        // Set up test markdown files
        $fixturesPath = base_path('tests/fixtures/markdown');
        $files = ['test_basic.md', 'test_sync.md', 'test_sync_updated.md', 'test_media.md', 'test_images.md'];
        foreach ($files as $file) {
            Storage::disk('local')->put($file, file_get_contents($fixturesPath.'/'.$file));
        }

        // Set up fake image files
        Storage::disk('local')->put('featured.jpg', 'fake image content');
        Storage::disk('local')->put('thumbnail.png', 'fake image content');
        Storage::disk('local')->put('image1.jpg', 'fake image content');
        Storage::disk('local')->put('image3.png', 'fake image content');
    }

    public function test_create_entry_from_markdown()
    {
        $model = Entry::createFromMarkdown(Storage::disk('local')->path('test_basic.md'), 'post');

        $this->assertEquals('Test Basic Markdown', $model->title);
        $this->assertEquals('This is the content of the basic markdown file.', trim($model->content));
        $this->assertEquals('test-basic', $model->slug);
        $this->assertEquals('2023-05-01 12:00:00', $model->published_at->format('Y-m-d H:i:s'));
        $this->assertIsString($model->meta['seo']['description']);
        $this->assertIsArray($model->meta['seo']['keywords']);
        $this->assertEquals(['tag1', 'tag2'], $model->tags->pluck('name')->toArray());
        $this->assertEquals('post', $model->type);
    }

    public function test_sync_entry_from_markdown()
    {
        $originalPath = Storage::disk('local')->path('test_sync.md');
        $updatedPath = Storage::disk('local')->path('test_sync_updated.md');

        // Initial sync
        $model = Entry::syncFromMarkdown($originalPath, 'post', true);

        $this->assertEquals('Initial Title', $model->title);
        $this->assertEquals('Initial content', trim($model->content));
        $this->assertEquals('test-sync', $model->slug);
        $this->assertEquals('post', $model->type);

        $initialId = $model->id;

        // Update the content and sync again
        file_put_contents($originalPath, file_get_contents($updatedPath));
        $updatedModel = Entry::syncFromMarkdown($originalPath, 'post', true);

        $this->assertEquals($initialId, $updatedModel->id, 'The updated model should have the same ID as the original');
        $this->assertEquals('Updated Title', $updatedModel->title);
        $this->assertEquals('Updated content', trim($updatedModel->content));
        $this->assertEquals('test-sync', $updatedModel->slug, 'The slug should not change during update');
        $this->assertEquals('post', $updatedModel->type);

        $this->assertEquals(1, Entry::count(), 'There should only be one model after sync');
    }

    public function test_handle_media_from_front_matter()
    {
        $imageManager = new ImageManager(new Driver);

        // Create test images
        $featuredImage = $imageManager->create(100, 100, function ($draw) {
            $draw->background('#ff0000');
        });
        Storage::disk('local')->put('featured.jpg', $featuredImage->toJpeg());

        $thumbnailImage = $imageManager->create(50, 50, function ($draw) {
            $draw->background('#00ff00');
        });
        Storage::disk('local')->put('thumbnail.png', $thumbnailImage->toPng());

        // Create markdown file with image references
        $content = "---\n";
        $content .= "type: post\n";
        $content .= "images.featured: featured.jpg\n";
        $content .= "images.thumbnail: thumbnail.png\n";
        $content .= "---\n";
        $content .= 'Test content';
        Storage::disk('local')->put('test_media.md', $content);

        $model = Entry::createFromMarkdown(Storage::disk('local')->path('test_media.md'));

        $this->assertEquals(2, $model->images()->count());
        $this->assertTrue($model->images()->get()->contains('collection', 'featured'));
        $this->assertTrue($model->images()->get()->contains('collection', 'thumbnail'));
        $this->assertEquals('post', $model->type);
    }

    public function test_process_markdown_images()
    {
        $imageManager = new ImageManager(new Driver);

        // Create test images
        $image1 = $imageManager->create(100, 100, function ($draw) {
            $draw->background('#ff0000');
        });
        Storage::disk('local')->put('image1.jpg', $image1->toJpeg());

        $image3 = $imageManager->create(100, 100, function ($draw) {
            $draw->background('#00ff00');
        });
        Storage::disk('local')->put('image3.png', $image3->toPng());

        // Create markdown file with image references
        $content = "---\n";
        $content .= "type: document\n";
        $content .= "---\n";
        $content .= "# Test Content\n";
        $content .= "![Alt Text 1](image1.jpg)\n";
        $content .= "![Alt Text 2](https://example.com/image2.jpg)\n";
        $content .= "![Alt Text 3](image3.png)\n";
        Storage::disk('local')->put('test_images.md', $content);

        $model = Entry::createFromMarkdown(Storage::disk('local')->path('test_images.md'), 'document');

        // Check if image paths are correctly updated
        $this->assertStringContainsString('![Alt Text 1]('.Storage::disk('local')->path('image1.jpg').')', $model->content);
        $this->assertStringContainsString('![Alt Text 2](https://example.com/image2.jpg)', $model->content);
        $this->assertStringContainsString('![Alt Text 3]('.Storage::disk('local')->path('image3.png').')', $model->content);

        $this->assertEquals(2, $model->images()->count());
        $this->assertTrue($model->images()->get()->contains('collection', 'content'));
        $this->assertEquals('document', $model->type);
    }

    public function test_published_at_is_set_when_true_in_front_matter()
    {
        $content = '---
title: Test Published Post
published_at: true
---
This is a test post with published_at set to true.';

        Storage::disk('local')->put('test_published.md', $content);

        $entry = Entry::createFromMarkdown(Storage::disk('local')->path('test_published.md'), 'post');

        $this->assertNotNull($entry->published_at);
        $this->assertEqualsWithDelta(now(), $entry->published_at, 1); // Allow 1 second difference
    }

    public function test_published_at_is_not_updated_for_existing_published_entries()
    {
        $content = '---
title: Test Already Published Post
published_at: true
---
This is a test post that was already published.';

        Storage::disk('local')->put('test_already_published.md', $content);

        $entry = Entry::createFromMarkdown(Storage::disk('local')->path('test_already_published.md'), 'post');
        $originalPublishedAt = $entry->published_at;

        // Simulate passage of time
        $this->travel(1)->hours();

        // Sync the entry again
        $updatedEntry = Entry::syncFromMarkdown(Storage::disk('local')->path('test_already_published.md'), 'post', true);

        $this->assertEquals($originalPublishedAt, $updatedEntry->published_at);
    }
}
