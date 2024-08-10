<?php

namespace Tests\Unit\Factories;

use App\Models\Image;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImageFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_basic_image()
    {
        $image = Image::factory()->create();

        $this->assertInstanceOf(Image::class, $image);
        $this->assertNotNull($image->filename);
        $this->assertNotNull($image->path);
        $this->assertNotNull($image->mime_type);
        $this->assertNotNull($image->size);
        $this->assertIsArray(json_decode($image->dimensions, true));
        $this->assertIsArray(json_decode($image->custom_properties, true));
        $this->assertNotNull($image->thumbhash);
    }

    public function test_creates_real_image()
    {
        $image = Image::factory()->withRealImage(800, 600)->create();

        $this->assertFileExists($image->path);
        $this->assertEquals('image/jpeg', $image->mime_type);
        $this->assertGreaterThan(0, $image->size);

        $dimensions = json_decode($image->dimensions, true);
        $this->assertEquals(800, $dimensions['width']);
        $this->assertEquals(600, $dimensions['height']);
    }

    public function test_creates_image_with_custom_dimensions()
    {
        $image = Image::factory()->withRealImage(1024, 768)->create();

        $dimensions = json_decode($image->dimensions, true);
        $this->assertEquals(1024, $dimensions['width']);
        $this->assertEquals(768, $dimensions['height']);
    }

    public function test_creates_image_with_custom_properties()
    {
        $customProperties = ['alt' => 'Custom alt text', 'title' => 'Custom title'];
        $image = Image::factory()->state(['custom_properties' => json_encode($customProperties)])->create();

        $this->assertEquals($customProperties, json_decode($image->custom_properties, true));
    }
}
