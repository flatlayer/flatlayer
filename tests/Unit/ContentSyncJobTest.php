<?php

namespace Tests\Unit;

use App\Jobs\ContentSyncJob;
use App\Models\ContentItem;
use App\Services\JinaSearchService;
use App\Services\SyncConfigurationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Mockery;

class ContentSyncJobTest extends TestCase
{
    use RefreshDatabase;

    protected $syncConfigService;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->syncConfigService = Mockery::mock(SyncConfigurationService::class);
        $this->app->instance(SyncConfigurationService::class, $this->syncConfigService);

        JinaSearchService::fake();
    }

    public function testContentSyncJobCreatesNewModels()
    {
        $this->createTestFile('test-post.md', "---\ntitle: Test Post\n---\nThis is a test post.");

        ContentSyncJob::dispatch(Storage::path('posts'), 'post', '*.md');

        $this->assertDatabaseHas('content_items', [
            'title' => 'Test Post',
            'content' => 'This is a test post.',
            'slug' => 'test-post',
            'type' => 'post',
        ]);
    }

    public function testContentSyncJobUpdatesExistingModels()
    {
        ContentItem::factory()->create([
            'title' => 'Existing Post',
            'content' => 'Old content',
            'slug' => 'existing-post',
            'type' => 'post',
        ]);

        $this->createTestFile('existing-post.md', "---\ntitle: Updated Post\n---\nThis is updated content.");

        ContentSyncJob::dispatch(Storage::path('posts'), 'post', '*.md');

        $this->assertDatabaseHas('content_items', [
            'title' => 'Updated Post',
            'content' => 'This is updated content.',
            'slug' => 'existing-post',
            'type' => 'post',
        ]);
    }

    public function testContentSyncJobDeletesRemovedModels()
    {
        ContentItem::factory()->create([
            'title' => 'Post to Delete',
            'content' => 'This post should be deleted',
            'slug' => 'post-to-delete',
            'type' => 'post',
        ]);

        $this->createTestFile('remaining-post.md', "---\ntitle: Remaining Post\n---\nThis post should remain.");

        ContentSyncJob::dispatch(Storage::path('posts'), 'post', '*.md');

        $this->assertDatabaseMissing('content_items', [
            'slug' => 'post-to-delete',
            'type' => 'post',
        ]);

        $this->assertDatabaseHas('content_items', [
            'title' => 'Remaining Post',
            'content' => 'This post should remain.',
            'slug' => 'remaining-post',
            'type' => 'post',
        ]);
    }

    public function testContentSyncJobHandlesMultipleFiles()
    {
        $this->createTestFile('post1.md', "---\ntitle: Post 1\n---\nContent 1");
        $this->createTestFile('post2.md', "---\ntitle: Post 2\n---\nContent 2");
        $this->createTestFile('post3.md', "---\ntitle: Post 3\n---\nContent 3");

        ContentSyncJob::dispatch(Storage::path('posts'), 'post', '*.md');

        $this->assertDatabaseCount('content_items', 3);
        $this->assertDatabaseHas('content_items', ['title' => 'Post 1', 'type' => 'post']);
        $this->assertDatabaseHas('content_items', ['title' => 'Post 2', 'type' => 'post']);
        $this->assertDatabaseHas('content_items', ['title' => 'Post 3', 'type' => 'post']);
    }

    public function testChunkedDeletion()
    {
        // Create a larger number of content items
        ContentItem::factory()->count(50)->create([
            'type' => 'post',
        ]);

        // Only create markdown files for some posts
        for ($i = 0; $i < 30; $i++) {
            $this->createTestFile("post-$i.md", "---\ntitle: Post $i\n---\nContent $i");
        }

        ContentSyncJob::dispatch(Storage::path('posts'), 'post', '*.md');

        // Check that only the posts with markdown files remain
        $this->assertDatabaseCount('content_items', 30);
        for ($i = 0; $i < 30; $i++) {
            $this->assertDatabaseHas('content_items', ['slug' => "post-$i", 'type' => 'post']);
        }
        for ($i = 30; $i < 50; $i++) {
            $this->assertDatabaseMissing('content_items', ['slug' => "post-$i", 'type' => 'post']);
        }
    }

    protected function createTestFile($filename, $content)
    {
        Storage::put("posts/$filename", $content);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
