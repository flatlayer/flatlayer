<?php

namespace Tests\Unit;

use App\Markdown\CustomMarkdownRenderer;
use App\Markdown\EnhancedMarkdownRenderer;
use App\Models\MediaFile;
use Tests\Fakes\FakePost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use Tests\TestCase;

class CustomMarkdownRendererTest extends TestCase
{
    use RefreshDatabase;

    protected $post;
    protected $customRenderer;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a fake post
        $this->post = FakePost::factory()->create([
            'title' => 'Test Post',
            'content' => "# Test Content\n\nThis is a test paragraph.\n\n![Test Image](/test-image.jpg This is a title)",
        ]);

        // Create a custom renderer instance
        $this->customRenderer = new CustomMarkdownRenderer($this->post);

        // Set up fake storage
        Storage::fake('public');
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
        // Create a fake media file
        $media = MediaFile::factory()->create([
            'model_type' => get_class($this->post),
            'model_id' => $this->post->id,
            'path' => '/test-image.jpg',
            'filename' => 'test-image.jpg',
            'collection' => 'images',
        ]);

        $enhancedRenderer = new EnhancedMarkdownRenderer($this->post);
        $imageNode = new Image('/test-image.jpg', 'Test Image');

        $result = $enhancedRenderer->render($imageNode);

        $this->assertInstanceOf(\League\CommonMark\Util\HtmlElement::class, $result);
        $this->assertEquals('div', $result->getTagName());
        $this->assertStringContainsString('markdown-image', $result->getAttribute('class'));
        $this->assertStringContainsString('<img', $result->getContents());
        $this->assertStringContainsString('alt="Test Image"', $result->getContents());
    }

    public function testGetParsedContent()
    {
        // Add a method to FakePost to mimic the behavior we're testing
        FakePost::macro('getParsedContent', function () {
            $renderer = new CustomMarkdownRenderer($this);
            return $renderer->convertToHtml($this->content);
        });

        $parsedContent = $this->post->getParsedContent();

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

        $enhancedRenderer = new EnhancedMarkdownRenderer($this->post);
        $invalidNode = new \League\CommonMark\Node\Block\Paragraph();

        $enhancedRenderer->render($invalidNode);
    }
}
