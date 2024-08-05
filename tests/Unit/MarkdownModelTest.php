<?php

namespace Tests\Unit;

use Tests\Fakes\TestMarkdownModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MarkdownModelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $migrationFile = require base_path('tests/database/migrations/create_test_markdown_models_table.php');
        (new $migrationFile)->up();

        Storage::fake('local');

        $fixturesPath = base_path('tests/fixtures/markdown');
        $files = ['test_basic.md', 'test_sync.md', 'test_sync_updated.md', 'test_media.md', 'test_images.md'];
        foreach ($files as $file) {
            Storage::disk('local')->put($file, file_get_contents($fixturesPath . '/' . $file));
        }

        Storage::disk('local')->put('featured.jpg', 'fake image content');
        Storage::disk('local')->put('thumbnail.png', 'fake image content');
        Storage::disk('local')->put('image1.jpg', 'fake image content');
        Storage::disk('local')->put('image3.png', 'fake image content');
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('test_markdown_models');
        parent::tearDown();
    }

    public function testFromMarkdown()
    {
        $model = TestMarkdownModel::fromMarkdown(Storage::disk('local')->path('test_basic.md'));

        $this->assertEquals('Test Basic Markdown', $model->title);
        $this->assertEquals("This is the content of the basic markdown file.", trim($model->content));
        $this->assertEquals('test-basic', $model->slug);
        $this->assertEquals('2023-05-01 12:00:00', $model->published_at->format('Y-m-d H:i:s'));
        $this->assertTrue($model->is_published);
        $this->assertEquals(['tag1', 'tag2'], $model->tags->pluck('name')->toArray());
    }

    public function testSyncFromMarkdown()
    {
        $originalPath = Storage::disk('local')->path('test_sync.md');
        $updatedPath = Storage::disk('local')->path('test_sync_updated.md');

        // First sync
        $model = TestMarkdownModel::syncFromMarkdown($originalPath, true);

        $this->assertEquals('Initial Title', $model->title);
        $this->assertEquals('Initial content', trim($model->content));
        $this->assertEquals('test-sync', $model->slug);

        // Store the initial ID
        $initialId = $model->id;

        // Update the content of the original file
        $updatedContent = file_get_contents($updatedPath);
        file_put_contents($originalPath, $updatedContent);

        // Second sync (update) using the same file
        $updatedModel = TestMarkdownModel::syncFromMarkdown($originalPath, true);

        $this->assertEquals($initialId, $updatedModel->id, "The updated model should have the same ID as the original");
        $this->assertEquals('Updated Title', $updatedModel->title);
        $this->assertEquals('Updated content', trim($updatedModel->content));
        $this->assertEquals('test-sync', $updatedModel->slug, "The slug should not change during update");

        $this->assertEquals(1, TestMarkdownModel::count(), "There should only be one model after sync");
    }

    public function testHandleMediaFromFrontMatter()
    {
        $model = TestMarkdownModel::fromMarkdown(Storage::disk('local')->path('test_media.md'));

        $this->assertCount(2, $model->media);
        $this->assertTrue($model->media->contains('collection', 'featured'));
        $this->assertTrue($model->media->contains('collection', 'thumbnail'));
    }

    public function testProcessMarkdownImages()
    {
        $model = TestMarkdownModel::fromMarkdown(Storage::disk('local')->path('test_images.md'));

        $this->assertStringContainsString('![Alt Text 1](image1.jpg)', $model->content);
        $this->assertStringContainsString('![Alt Text 2](https://example.com/image2.jpg)', $model->content);
        $this->assertStringContainsString('![Alt Text 3](image3.png)', $model->content);

        $this->assertCount(2, $model->media);
        $this->assertTrue($model->media->contains('collection', 'images'));
    }
}
