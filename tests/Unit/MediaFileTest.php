<?php

namespace Tests\Unit;

use App\Models\Image;
use App\Models\Entry;
use App\Services\ImageService;
use App\Services\JinaSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaFileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        JinaSearchService::fake();
    }

    public function testAddMediaToModel()
    {
        $contentItem = Entry::factory()->create(['type' => 'post']);
        $file = UploadedFile::fake()->image('test.jpg', 100, 100);
        $path = $file->store('test');

        $media = $contentItem->addImage(Storage::path($path));

        $this->assertDatabaseHas('images', [
            'entry_id' => $contentItem->id,
            'collection' => 'default',
        ]);

        // Verify the media record
        $this->assertEquals($contentItem->id, $media->entry_id);
        $this->assertEquals('default', $media->collection);
        $this->assertNotNull($media->filename);
        $this->assertNotNull($media->thumbhash);
        $this->assertEquals(['width' => 100, 'height' => 100], $media->dimensions);
    }

    public function testSyncMedia()
    {
        $contentItem = Entry::factory()->create(['type' => 'post']);
        $file1 = UploadedFile::fake()->image('test1.jpg', 100, 100);
        $file2 = UploadedFile::fake()->image('test2.jpg', 200, 200);
        $path1 = $file1->store('test');
        $path2 = $file2->store('test');

        // Add initial media
        $media1 = $contentItem->addImage(Storage::path($path1));
        $media2 = $contentItem->addImage(Storage::path($path2));

        // Sync media (removing test2.jpg and adding test3.jpg)
        $file3 = UploadedFile::fake()->image('test3.jpg', 300, 300);
        $path3 = $file3->store('test');
        $contentItem->syncImages([Storage::path($path1), Storage::path($path3)]);

        $this->assertDatabaseHas('images', ['id' => $media1->id]);
        $this->assertDatabaseMissing('images', ['id' => $media2->id]);
        $this->assertDatabaseHas('images', [
            'entry_id' => $contentItem->id,
            'dimensions' => json_encode(['width' => 300, 'height' => 300])
        ]);
    }

    public function testUpdateOrCreateMedia()
    {
        $contentItem = Entry::factory()->create(['type' => 'post']);
        $file = UploadedFile::fake()->image('test.jpg', 100, 100);
        $path = $file->store('test');
        $fullPath = Storage::path($path);

        // Create new media
        $media = $contentItem->updateOrCreateImage($fullPath);
        $this->assertDatabaseHas('images', [
            'entry_id' => $contentItem->id,
            'dimensions' => json_encode(['width' => 100, 'height' => 100]),
            'path' => $fullPath,
        ]);
        $originalDimensions = $media->dimensions;
        $originalThumbhash = $media->thumbhash;

        // Update existing media with a new file
        $newFile = UploadedFile::fake()->image('test_new.jpg', 200, 200);
        $newPath = $newFile->store('test');
        $newFullPath = Storage::path($newPath);
        Storage::delete($path);
        Storage::move($newPath, $path);

        $updatedMedia = $contentItem->updateOrCreateImage($fullPath);

        $this->assertEquals($media->id, $updatedMedia->id);
        $this->assertNotEquals($originalDimensions, $updatedMedia->dimensions);
        $this->assertEquals(['width' => 200, 'height' => 200], $updatedMedia->dimensions);

        // Check that the thumbhash exists and is a string
        $this->assertNotNull($updatedMedia->thumbhash);
        $this->assertIsString($updatedMedia->thumbhash);

        // Verify that only one media record exists for this content item
        $this->assertEquals(1, $contentItem->images()->count());
    }

    public function testGetFileInfo()
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

    public function testGenerateThumbhash()
    {
        $file = UploadedFile::fake()->image('test.jpg', 100, 100);
        $path = $file->store('test');

        $service = app(ImageService::class);
        $thumbhash = $service->generateThumbhash(Storage::path($path));

        $this->assertNotNull($thumbhash);
        $this->assertIsString($thumbhash);
    }

    public function testGetImgTagIncludesThumbhash()
    {
        $contentItem = Entry::factory()->create(['type' => 'post']);
        $file = UploadedFile::fake()->image('test.jpg', 100, 100);
        $path = $file->store('test');

        $media = $contentItem->addImage(Storage::path($path));
        $media->thumbhash = 'fake_thumbhash_value';
        $media->save();

        $sizes = ['100vw', 'md:75vw', 'lg:50vw'];
        $imgTag = $media->getImgTag($sizes);

        $this->assertStringContainsString('data-thumbhash="fake_thumbhash_value"', $imgTag);
        $this->assertStringContainsString('sizes="(min-width: 1024px) 50vw, (min-width: 768px) 75vw, 100vw"', $imgTag);
    }

    /**
     * Call protected/private method of a class.
     *
     * @param object|string $object
     * @param string $methodName
     * @param array $parameters
     * @return mixed
     * @throws \ReflectionException
     */
    protected function invokeMethod($object, string $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(is_string($object) ? $object : get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invokeArgs(is_string($object) ? null : $object, $parameters);
    }
}
