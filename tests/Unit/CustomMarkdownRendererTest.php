<?php

namespace Tests\Unit;

use App\Markdown\CustomImageRenderer;
use App\Models\MediaFile;
use App\Models\ContentItem;
use App\Services\JinaSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use Mockery;
use Tests\TestCase;

class CustomMarkdownRendererTest extends TestCase
{
    use RefreshDatabase;

    protected $contentItem;
    protected $customRenderer;

    protected function setUp(): void
    {
        parent::setUp();

        JinaSearchService::fake();

        // Create a content item
        $this->contentItem = ContentItem::factory()->create([
            'type' => 'post',
            'title' => 'Test Post',
            'content' => "# Test Content\n\nThis is a test paragraph.\n\n![Test Image](/test-image.jpg This is a title)",
        ]);

        // Set up the Environment
        $this->environment = new Environment([
            'allow_unsafe_links' => false,
        ]);
        $this->environment->addExtension(new CommonMarkCoreExtension());

        // Create a custom renderer instance
        $this->customRenderer = new CustomImageRenderer($this->contentItem, $this->environment);

        // Set up fake storage
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

    public function testConvertToHtml()
    {
        $markdown = "# Test Header\n\nThis is a test paragraph.";
        $html = $this->customRenderer->convertToHtml($markdown);

        $this->assertStringContainsString('<h1>Test Header</h1>', $html);
        $this->assertStringContainsString('<p>This is a test paragraph.</p>', $html);
    }

    public function testEnhancedImageRendering()
    {
        // Create a media file
        $media = $this->contentItem->addMedia(Storage::disk('public')->path('test-image.jpg'), 'images');

        $enhancedRenderer = new CustomImageRenderer($this->contentItem, $this->environment);
        $imageNode = new Image(
            Storage::disk('public')->path('test-image.jpg'),
            'Test Image'
        );

        // Mock the ChildNodeRendererInterface
        $childRenderer = Mockery::mock(ChildNodeRendererInterface::class);
        $childRenderer->shouldReceive('renderNodes')->andReturn('');

        $result = $enhancedRenderer->render($imageNode, $childRenderer);

        $this->assertInstanceOf(\League\CommonMark\Util\HtmlElement::class, $result);
        $this->assertEquals('div', $result->getTagName());
        $this->assertStringContainsString('markdown-image', $result->getAttribute('class'));
        $this->assertStringContainsString('<img', $result->getContents());
        $this->assertStringContainsString('alt="Test Image"', $result->getContents());
    }

    public function testGetParsedContent()
    {
        $parsedContent = $this->contentItem->getParsedContent();

        $this->assertStringContainsString('<h1>Test Content</h1>', $parsedContent);
        $this->assertStringContainsString('<p>This is a test paragraph.</p>', $parsedContent);

        // Because we don't have a media file, the image should not be rendered
        $this->assertStringNotContainsString('<div class="markdown-image">', $parsedContent);
    }

    public function testExternalImageFallback()
    {
        $externalMarkdown = "![External Image](https://example.com/image.jpg)";
        $html = $this->customRenderer->convertToHtml($externalMarkdown);

        $this->assertStringContainsString('<img src="https://example.com/image.jpg"', $html);
        $this->assertStringNotContainsString('markdown-image', $html);
    }

    public function testInvalidNodeType()
    {
        $this->expectException(\InvalidArgumentException::class);

        $enhancedRenderer = new CustomImageRenderer($this->contentItem);
        $invalidNode = new \League\CommonMark\Node\Block\Paragraph();

        $enhancedRenderer->render($invalidNode);
    }
}
