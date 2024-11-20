<?php

namespace Tests\Unit\Services\Media;

use App\Models\Entry;
use App\Services\Media\MediaLibrary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Traits\CreatesTestFiles;

class MediaLibraryTest extends TestCase
{
    use CreatesTestFiles, RefreshDatabase;

    protected Entry $entry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupTestFiles();

        // Create a test entry and configure it to use our test disk
        $this->entry = Entry::factory()->create(['type' => 'post']);
        $this->entry->useImageDisk($this->disk);

        // Create test images directory
        $this->disk->makeDirectory('images');
    }

    public function test_add_image_to_entry()
    {
        // Create a test image
        $this->createImage('images/test.jpg', 100, 100);

        // Add image to entry
        $image = $this->entry->addImage('images/test.jpg');

        // Verify database state
        $this->assertDatabaseHas('images', [
            'entry_id' => $this->entry->id,
            'collection' => 'default',
            'filename' => 'test.jpg',
        ]);

        // Verify image attributes
        $this->assertEquals($this->entry->id, $image->entry_id);
        $this->assertEquals('default', $image->collection);
        $this->assertEquals(['width' => 100, 'height' => 100], $image->dimensions);
        $this->assertNotNull($image->thumbhash);
        $this->assertIsString($image->thumbhash);
    }

    public function test_sync_images()
    {
        // Create test images
        $this->createImage('images/test1.jpg', 100, 100);
        $this->createImage('images/test2.jpg', 200, 200);
        $this->createImage('images/test3.jpg', 300, 300);

        // Add initial images
        $image1 = $this->entry->addImage('images/test1.jpg');
        $image2 = $this->entry->addImage('images/test2.jpg');

        // Sync with new set of images
        $this->entry->syncImages(['images/test1.jpg', 'images/test3.jpg']);

        // Verify database state
        $this->assertDatabaseHas('images', ['id' => $image1->id]);
        $this->assertDatabaseMissing('images', ['id' => $image2->id]);
        $this->assertDatabaseHas('images', [
            'entry_id' => $this->entry->id,
            'filename' => 'test3.jpg',
            'dimensions' => json_encode(['width' => 300, 'height' => 300]),
        ]);
    }

    public function test_update_or_create_image()
    {
        // Create initial test image
        $this->createImage('images/test.jpg', 100, 100);

        // Create initial image record
        $image = $this->entry->updateOrCreateImage('images/test.jpg');

        // Verify initial state
        $this->assertDatabaseHas('images', [
            'entry_id' => $this->entry->id,
            'dimensions' => json_encode(['width' => 100, 'height' => 100]),
        ]);

        $originalDimensions = $image->dimensions;

        // Replace with new image
        $this->createImage('images/test.jpg', 200, 200);

        // Update the image
        $updatedImage = $this->entry->updateOrCreateImage('images/test.jpg');

        // Verify update results
        $this->assertEquals($image->id, $updatedImage->id);
        $this->assertNotEquals($originalDimensions, $updatedImage->dimensions);
        $this->assertEquals(['width' => 200, 'height' => 200], $updatedImage->dimensions);
        $this->assertNotNull($updatedImage->thumbhash);
        $this->assertIsString($updatedImage->thumbhash);
        $this->assertEquals(1, $this->entry->images()->count());
    }

    public function test_get_images_by_collection()
    {
        // Create test images in different collections
        $this->createImage('images/featured.jpg', 100, 100);
        $this->createImage('images/gallery1.jpg', 200, 200);
        $this->createImage('images/gallery2.jpg', 200, 200);

        // Add images to different collections
        $this->entry->addImage('images/featured.jpg', 'featured');
        $this->entry->addImage('images/gallery1.jpg', 'gallery');
        $this->entry->addImage('images/gallery2.jpg', 'gallery');

        // Test retrieving images by collection
        $featuredImages = $this->entry->getImages('featured');
        $galleryImages = $this->entry->getImages('gallery');

        $this->assertCount(1, $featuredImages);
        $this->assertCount(2, $galleryImages);
        $this->assertEquals('featured.jpg', $featuredImages->first()->filename);
    }

    public function test_clear_image_collection()
    {
        // Create and add test images
        $this->createImage('images/test1.jpg', 100, 100);
        $this->createImage('images/test2.jpg', 100, 100);

        $this->entry->addImage('images/test1.jpg', 'gallery');
        $this->entry->addImage('images/test2.jpg', 'gallery');

        // Clear the collection
        $this->entry->clearImageCollection('gallery');

        $this->assertCount(0, $this->entry->getImages('gallery'));
    }

    public function test_get_image_service_uses_configured_disk()
    {
        $customDisk = Storage::fake('custom');
        $this->entry->useImageDisk($customDisk);

        $reflection = new \ReflectionClass($this->entry);
        $method = $reflection->getMethod('getImageService');
        $method->setAccessible(true);

        /** @var MediaLibrary $service */
        $service = $method->invoke($this->entry);

        $reflection = new \ReflectionClass($service);
        $property = $reflection->getProperty('storage');
        $property->setAccessible(true);

        /** @var MediaStorage $storage */
        $storage = $property->getValue($service);

        $storageReflection = new \ReflectionClass($storage);
        $diskProperty = $storageReflection->getProperty('disk');
        $diskProperty->setAccessible(true);

        $this->assertSame($customDisk, $diskProperty->getValue($storage));
    }

    public function test_use_image_disk_accepts_string()
    {
        Storage::fake('custom');
        $this->entry->useImageDisk('custom');

        $reflection = new \ReflectionClass($this->entry);
        $method = $reflection->getMethod('getImageDisk');
        $method->setAccessible(true);

        $disk = $method->invoke($this->entry);
        $this->assertSame(Storage::disk('custom'), $disk);
    }

    protected function tearDown(): void
    {
        $this->tearDownTestFiles();
        parent::tearDown();
    }
}
