<?php

namespace Tests\Unit;

use App\Jobs\EntrySyncJob;
use App\Models\Entry;
use App\Services\SyncConfigurationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Mockery;

class EntrySyncJobTest extends TestCase
{
    use RefreshDatabase;

    protected $syncConfigService;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->syncConfigService = Mockery::mock(SyncConfigurationService::class);
        $this->app->instance(SyncConfigurationService::class, $this->syncConfigService);
    }

    public function test_sync_job_creates_new_entries()
    {
        $this->createTestFile('test-post.md', "---\ntitle: Test Post\n---\nThis is a test post.");

        EntrySyncJob::dispatch(Storage::path('posts'), 'post', '*.md');

        $this->assertDatabaseHas('entries', [
            'title' => 'Test Post',
            'content' => 'This is a test post.',
            'slug' => 'test-post',
            'type' => 'post',
        ]);
    }

    public function test_sync_job_updates_existing_entries()
    {
        Entry::factory()->create([
            'title' => 'Existing Post',
            'content' => 'Old content',
            'slug' => 'existing-post',
            'type' => 'post',
        ]);

        $this->createTestFile('existing-post.md', "---\ntitle: Updated Post\n---\nThis is updated content.");

        EntrySyncJob::dispatch(Storage::path('posts'), 'post', '*.md');

        $this->assertDatabaseHas('entries', [
            'title' => 'Updated Post',
            'content' => 'This is updated content.',
            'slug' => 'existing-post',
            'type' => 'post',
        ]);
    }

    public function test_sync_job_deletes_removed_entries()
    {
        Entry::factory()->create([
            'title' => 'Post to Delete',
            'content' => 'This post should be deleted',
            'slug' => 'post-to-delete',
            'type' => 'post',
        ]);

        $this->createTestFile('remaining-post.md', "---\ntitle: Remaining Post\n---\nThis post should remain.");

        EntrySyncJob::dispatch(Storage::path('posts'), 'post', '*.md');

        $this->assertDatabaseMissing('entries', [
            'slug' => 'post-to-delete',
            'type' => 'post',
        ]);

        $this->assertDatabaseHas('entries', [
            'title' => 'Remaining Post',
            'content' => 'This post should remain.',
            'slug' => 'remaining-post',
            'type' => 'post',
        ]);
    }

    public function test_sync_job_handles_multiple_files()
    {
        $this->createTestFile('post1.md', "---\ntitle: Post 1\n---\nContent 1");
        $this->createTestFile('post2.md', "---\ntitle: Post 2\n---\nContent 2");
        $this->createTestFile('post3.md', "---\ntitle: Post 3\n---\nContent 3");

        EntrySyncJob::dispatch(Storage::path('posts'), 'post', '*.md');

        $this->assertDatabaseCount('entries', 3);
        $this->assertDatabaseHas('entries', ['title' => 'Post 1', 'type' => 'post']);
        $this->assertDatabaseHas('entries', ['title' => 'Post 2', 'type' => 'post']);
        $this->assertDatabaseHas('entries', ['title' => 'Post 3', 'type' => 'post']);
    }

    public function test_sync_job_performs_chunked_deletion()
    {
        // Create a large number of entries
        Entry::factory()->count(50)->create(['type' => 'post']);

        // Create markdown files for only some of the entries
        for ($i = 0; $i < 30; $i++) {
            $this->createTestFile("post-$i.md", "---\ntitle: Post $i\n---\nContent $i");
        }

        EntrySyncJob::dispatch(Storage::path('posts'), 'post', '*.md');

        // Verify that only entries with corresponding markdown files remain
        $this->assertDatabaseCount('entries', 30);
        for ($i = 0; $i < 30; $i++) {
            $this->assertDatabaseHas('entries', ['slug' => "post-$i", 'type' => 'post']);
        }
        for ($i = 30; $i < 50; $i++) {
            $this->assertDatabaseMissing('entries', ['slug' => "post-$i", 'type' => 'post']);
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
