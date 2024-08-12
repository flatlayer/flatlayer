<?php

namespace Tests\Unit;

use App\Models\Entry;
use App\Services\ImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImageModelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_add_image_to_entry()
    {
        $entry = Entry::factory()->create(['type' => 'post']);
        $file = UploadedFile::fake()->image('test.jpg', 100, 100);
        $path = $file->store('test');

        $image = $entry->addImage(Storage::path($path));

        $this->assertDatabaseHas('images', [
            'entry_id' => $entry->id,
            'collection' => 'default',
        ]);

        $this->assertEquals($entry->id, $image->entry_id);
        $this->assertEquals('default', $image->collection);
        $this->assertNotNull($image->filename);
        $this->assertNotNull($image->thumbhash);
        $this->assertEquals(['width' => 100, 'height' => 100], $image->dimensions);
    }

    public function test_sync_images()
    {
        $entry = Entry::factory()->create(['type' => 'post']);
        $file1 = UploadedFile::fake()->image('test1.jpg', 100, 100);
        $file2 = UploadedFile::fake()->image('test2.jpg', 200, 200);
        $path1 = $file1->store('test');
        $path2 = $file2->store('test');

        $image1 = $entry->addImage(Storage::path($path1));
        $image2 = $entry->addImage(Storage::path($path2));

        $file3 = UploadedFile::fake()->image('test3.jpg', 300, 300);
        $path3 = $file3->store('test');
        $entry->syncImages([Storage::path($path1), Storage::path($path3)]);

        $this->assertDatabaseHas('images', ['id' => $image1->id]);
        $this->assertDatabaseMissing('images', ['id' => $image2->id]);
        $this->assertDatabaseHas('images', [
            'entry_id' => $entry->id,
            'dimensions' => json_encode(['width' => 300, 'height' => 300]),
        ]);
    }

    public function test_update_or_create_image()
    {
        $entry = Entry::factory()->create(['type' => 'post']);
        $file = UploadedFile::fake()->image('test.jpg', 100, 100);
        $path = $file->store('test');
        $fullPath = Storage::path($path);

        $image = $entry->updateOrCreateImage($fullPath);
        $this->assertDatabaseHas('images', [
            'entry_id' => $entry->id,
            'dimensions' => json_encode(['width' => 100, 'height' => 100]),
            'path' => $fullPath,
        ]);
        $originalDimensions = $image->dimensions;

        $newFile = UploadedFile::fake()->image('test_new.jpg', 200, 200);
        $newPath = $newFile->store('test');
        Storage::delete($path);
        Storage::move($newPath, $path);

        $updatedImage = $entry->updateOrCreateImage($fullPath);

        $this->assertEquals($image->id, $updatedImage->id);
        $this->assertNotEquals($originalDimensions, $updatedImage->dimensions);
        $this->assertEquals(['width' => 200, 'height' => 200], $updatedImage->dimensions);
        $this->assertNotNull($updatedImage->thumbhash);
        $this->assertIsString($updatedImage->thumbhash);

        $this->assertEquals(1, $entry->images()->count());
    }

    public function test_get_file_info()
    {
        $file = UploadedFile::fake()->image('test.jpg', 100, 100);
        $path = $file->store('test');

        $service = app(ImageService::class);
        $fileInfo = $service->getFileInfo(Storage::path($path));

        $this->assertArrayHasKey('size', $fileInfo);
        $this->assertArrayHasKey('mime_type', $fileInfo);
        $this->assertArrayHasKey('dimensions', $fileInfo);
        $this->assertArrayHasKey('thumbhash', $fileInfo);
        $this->assertEquals(['width' => 100, 'height' => 100], $fileInfo['dimensions']);
        $this->assertNotNull($fileInfo['thumbhash']);
    }

    public function test_generate_thumbhash()
    {
        $file = UploadedFile::fake()->image('test.jpg', 100, 100);
        $path = $file->store('test');

        $service = app(ImageService::class);
        $thumbhash = $service->generateThumbhash(Storage::path($path));

        $this->assertNotNull($thumbhash);
        $this->assertIsString($thumbhash);
    }
}
