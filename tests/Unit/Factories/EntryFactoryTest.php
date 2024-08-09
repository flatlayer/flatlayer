<?php

namespace Tests\Unit\Factories;

use App\Models\Entry;
use Database\Factories\EntryFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EntryFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_basic_entry()
    {
        $entry = Entry::factory()->create();

        $this->assertInstanceOf(Entry::class, $entry);
        $this->assertNotNull($entry->title);
        $this->assertNotNull($entry->slug);
        $this->assertNotNull($entry->content);
        $this->assertNotNull($entry->published_at);
        $this->assertIsArray($entry->meta);
    }

    public function test_creates_unpublished_entry()
    {
        $entry = Entry::factory()->unpublished()->create();

        $this->assertNull($entry->published_at);
    }

    public function test_creates_post()
    {
        $entry = Entry::factory()->post()->create();

        $this->assertEquals('post', $entry->type);
        $this->assertArrayHasKey('comments_count', $entry->meta);
        $this->assertArrayHasKey('likes_count', $entry->meta);
    }

    public function test_creates_document()
    {
        $entry = Entry::factory()->document()->create();

        $this->assertEquals('document', $entry->type);
        $this->assertArrayHasKey('document_type', $entry->meta);
        $this->assertArrayHasKey('target_audience', $entry->meta);
    }

    public function test_attaches_tags()
    {
        $entry = Entry::factory()->withTags(['tag1', 'tag2'])->create();

        $this->assertCount(2, $entry->tags);
        $this->assertTrue($entry->tags->contains('name', 'tag1'));
        $this->assertTrue($entry->tags->contains('name', 'tag2'));
    }

    public function test_creates_real_markdown()
    {
        Storage::fake('local');

        $entry = Entry::factory()->withRealMarkdown(2)->create();

        $this->assertFileExists(Storage::disk('local')->path($entry->filename));
        $content = file_get_contents(Storage::disk('local')->path($entry->filename));

        $this->assertStringContainsString('---', $content); // Front matter
        $this->assertStringContainsString('# ', $content); // Heading
        $this->assertStringContainsString('![Image 1](image1.jpg)', $content); // Image
        $this->assertStringContainsString('![Image 2](image2.jpg)', $content); // Image
    }

    public function test_creates_entry_with_images()
    {
        $entry = Entry::factory()->withImages(3)->create();

        $this->assertCount(3, $entry->images);
        $this->assertCount(3, $entry->meta['images']);
    }

    public function test_creates_entry_with_real_images()
    {
        $entry = Entry::factory()->withImages(2, true)->create();

        $this->assertCount(2, $entry->images);
        $this->assertCount(2, $entry->meta['images']);

        foreach ($entry->images as $image) {
            $this->assertFileExists($image->path);
            $this->assertEquals('image/jpeg', $image->mime_type);
        }
    }
}
