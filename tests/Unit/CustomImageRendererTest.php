<?php

namespace Tests\Unit;

use App\Markdown\CustomImageRenderer;
use App\Models\MediaFile;
use App\Models\Entry;
use App\Services\JinaSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use Mockery;
use Tests\TestCase;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class CustomImageRendererTest extends TestCase
{
    use RefreshDatabase;

    protected $contentItem;
    protected $environment;

    protected function setUp(): void
    {
        parent::setUp();

        JinaSearchService::fake();

        $this->contentItem = Entry::factory()->create([
            'type' => 'post',
            'title' => 'Test Post',
        ]);

        $this->environment = new Environment([
            'allow_unsafe_links' => false,
        ]);

        Storage::fake('public');

        // Create a test image
        $this->createTestImage();
    }

    protected function createTestImage()
    {
        $manager = new ImageManager(new Driver());
        $image = $manager->create(100, 100, function ($draw) {
            $draw->background('#000000');
            $draw->text('Test', 50, 50, function ($font) {
                $font->color('#ffffff');
                $font->align('center');
                $font->valign('middle');
            });
        });

        Storage::disk('public')->put('test-image.jpg', $image->toJpeg());
    }

    public function testEnhancedImageRendering()
    {
        $media = $this->contentItem->addMedia(Storage::disk('public')->path('test-image.jpg'), 'images');

        $enhancedRenderer = new CustomImageRenderer($this->contentItem, $this->environment);
        $imageNode = new Image(
            Storage::disk('public')->path('test-image.jpg'),
            'Test Image'
        );

        $childRenderer = Mockery::mock(ChildNodeRendererInterface::class);
        $childRenderer->shouldReceive('renderNodes')->andReturn('');

        $result = $enhancedRenderer->render($imageNode, $childRenderer);

        $this->assertInstanceOf(\League\CommonMark\Util\HtmlElement::class, $result);
        $this->assertEquals('div', $result->getTagName());
        $this->assertStringContainsString('markdown-image', $result->getAttribute('class'));
        $this->assertStringContainsString('<img', $result->getContents());
        $this->assertStringContainsString('alt="Test Image"', $result->getContents());
    }

    public function testExternalImageFallback()
    {
        $enhancedRenderer = new CustomImageRenderer($this->contentItem, $this->environment);
        $imageNode = new Image('https://example.com/image.jpg', 'External Image');

        $childRenderer = Mockery::mock(ChildNodeRendererInterface::class);
        $childRenderer->shouldReceive('renderNodes')->andReturn('');

        $result = $enhancedRenderer->render($imageNode, $childRenderer);

        $this->assertInstanceOf(\League\CommonMark\Util\HtmlElement::class, $result);
        $this->assertEquals('img', $result->getTagName());
        $this->assertEquals('https://example.com/image.jpg', $result->getAttribute('src'));
        $this->assertEquals('External Image', $result->getAttribute('alt'));
    }

    // Add more tests specific to CustomImageRenderer
}
