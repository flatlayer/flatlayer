<?php

namespace Tests\Unit;

use App\Jobs\EntrySyncJob;
use App\Models\Entry;
use App\Models\Image;
use App\Services\SyncConfigurationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

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
        $this->fakeOpenAi(50);

        // Create markdown files for only some of the entries
        for ($i = 0; $i < 20; $i++) {
            $this->createTestFile("post-$i.md", "---\ntitle: Post $i\n---\nContent $i");
        }

        EntrySyncJob::dispatchSync(Storage::path('posts'), 'post', '*.md');

        // Verify that only entries with corresponding markdown files remain
        $this->assertDatabaseCount('entries', 30);
        for ($i = 0; $i < 30; $i++) {
            $this->assertDatabaseHas('entries', ['slug' => "post-$i", 'type' => 'post']);
        }
        for ($i = 30; $i < 50; $i++) {
            $this->assertDatabaseMissing('entries', ['slug' => "post-$i", 'type' => 'post']);
        }
    }

    public function test_sync_job_handles_entries_with_tags_and_images()
    {
        // Create test images using ImageFactory
        $image1 = Image::factory()->withRealImage(640, 480)->create();
        $image2 = Image::factory()->withRealImage(800, 600)->create();
        $image3 = Image::factory()->withRealImage(1024, 768)->create();

        // Move the created images to the posts directory
        Storage::disk('local')->put('posts/'.$image1->filename, file_get_contents($image1->path));
        Storage::disk('local')->put('posts/'.$image2->filename, file_get_contents($image2->path));
        Storage::disk('local')->put('posts/'.$image3->filename, file_get_contents($image3->path));

        // Create the markdown file with references to the real images
        $this->createTestFile('post-with-tags-and-images.md', "---
title: Test Post
tags: [tag1, tag2]
images.featured: {$image1->filename}
images.gallery: [{$image2->filename}, {$image3->filename}]
---
This is a test post with tags and images.");

        EntrySyncJob::dispatch(Storage::disk('local')->path('posts'), 'post', '*.md');

        $entry = Entry::where('slug', 'post-with-tags-and-images')->first();

        $this->assertNotNull($entry);
        $this->assertEquals('Test Post', $entry->title);
        $this->assertEquals(['tag1', 'tag2'], $entry->tags->pluck('name')->toArray());

        $this->assertEquals(3, $entry->images()->count());
        $this->assertDatabaseHas('images', ['entry_id' => $entry->id, 'collection' => 'featured']);
        $this->assertDatabaseHas('images', ['entry_id' => $entry->id, 'collection' => 'gallery']);

        // Additional checks for image properties
        $featuredImage = $entry->images()->where('collection', 'featured')->first();
        $this->assertEquals(640, $featuredImage->dimensions['width']);
        $this->assertEquals(480, $featuredImage->dimensions['height']);

        $galleryImages = $entry->images()->where('collection', 'gallery')->get();
        $this->assertCount(2, $galleryImages);
        $this->assertTrue($galleryImages->contains(function ($image) {
            return $image->dimensions['width'] == 800 && $image->dimensions['height'] == 600;
        }));
        $this->assertTrue($galleryImages->contains(function ($image) {
            return $image->dimensions['width'] == 1024 && $image->dimensions['height'] == 768;
        }));
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
