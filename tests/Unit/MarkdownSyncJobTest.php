<?php

namespace Tests\Unit;

use App\Jobs\MarkdownSyncJob;
use Tests\Fakes\FakePost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ReflectionClass;

class MarkdownSyncJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        Config::set('flatlayer.models.Tests\\Fakes\\FakePost', [
            'path' => Storage::path('posts'),
            'source' => '*.md'
        ]);
    }

    public function testMarkdownSyncJobCreatesNewModels()
    {
        Storage::put('posts/test-post.md', "---\ntitle: Test Post\n---\nThis is a test post.");

        $job = new MarkdownSyncJob(FakePost::class);
        $job->handle();

        $this->assertDatabaseHas('posts', [
            'title' => 'Test Post',
            'content' => 'This is a test post.',
            'slug' => 'test-post',
        ]);
    }

    public function testMarkdownSyncJobUpdatesExistingModels()
    {
        FakePost::create([
            'title' => 'Existing Post',
            'content' => 'Old content',
            'slug' => 'existing-post',
        ]);

        Storage::put('posts/existing-post.md', "---\ntitle: Updated Post\n---\nThis is updated content.");

        $job = new MarkdownSyncJob(FakePost::class);
        $job->handle();

        $this->assertDatabaseHas('posts', [
            'title' => 'Updated Post',
            'content' => 'This is updated content.',
            'slug' => 'existing-post',
        ]);
    }

    public function testMarkdownSyncJobDeletesRemovedModels()
    {
        FakePost::create([
            'title' => 'Post to Delete',
            'content' => 'This post should be deleted',
            'slug' => 'post-to-delete',
        ]);

        Storage::put('posts/remaining-post.md', "---\ntitle: Remaining Post\n---\nThis post should remain.");

        $job = new MarkdownSyncJob(FakePost::class);
        $job->handle();

        $this->assertDatabaseMissing('posts', [
            'slug' => 'post-to-delete',
        ]);

        $this->assertDatabaseHas('posts', [
            'title' => 'Remaining Post',
            'content' => 'This post should remain.',
            'slug' => 'remaining-post',
        ]);
    }

    public function testMarkdownSyncJobHandlesMultipleFiles()
    {
        Storage::put('posts/post1.md', "---\ntitle: Post 1\n---\nContent 1");
        Storage::put('posts/post2.md', "---\ntitle: Post 2\n---\nContent 2");
        Storage::put('posts/post3.md', "---\ntitle: Post 3\n---\nContent 3");

        $job = new MarkdownSyncJob(FakePost::class);
        $job->handle();

        $this->assertDatabaseCount('posts', 3);
        $this->assertDatabaseHas('posts', ['title' => 'Post 1']);
        $this->assertDatabaseHas('posts', ['title' => 'Post 2']);
        $this->assertDatabaseHas('posts', ['title' => 'Post 3']);
    }

    public function testChunkedDeletion()
    {
        // Create a larger number of posts
        for ($i = 0; $i < 50; $i++) {
            FakePost::create([
                'title' => "Post $i",
                'content' => "Content $i",
                'slug' => "post-$i",
            ]);
        }

        // Only create markdown files for some posts
        for ($i = 0; $i < 30; $i++) {
            Storage::put("posts/post-$i.md", "---\ntitle: Post $i\n---\nContent $i");
        }

        $job = new MarkdownSyncJob(FakePost::class);
        $job->handle();

        // Check that only the posts with markdown files remain
        $this->assertDatabaseCount('posts', 30);
        for ($i = 0; $i < 30; $i++) {
            $this->assertDatabaseHas('posts', ['slug' => "post-$i"]);
        }
        for ($i = 30; $i < 50; $i++) {
            $this->assertDatabaseMissing('posts', ['slug' => "post-$i"]);
        }
    }
}
