<?php

namespace Tests\Unit\Services\Markdown;

use App\Services\Markdown\MarkdownLinkProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tests\Traits\CreatesTestFiles;

class MarkdownLinkProcessorTest extends TestCase
{
    use CreatesTestFiles, RefreshDatabase;

    private MarkdownLinkProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupTestFiles();
        $this->processor = new MarkdownLinkProcessor();
    }

    /**
     * Helper method to verify markdown link resolution
     */
    protected function assertLinkResolvesTo(
        string $sourcePath,
        string $markdownLink,
        string $expectedResolution
    ): void {
        $content = "# Test\n\n[Test]({$markdownLink})";
        $processed = $this->processor->processLinks($content, $sourcePath);
        $this->assertEquals("# Test\n\n[Test]({$expectedResolution})", $processed);
    }

    /**
     * Data provider for link resolution tests
     */
    public static function linkResolutionProvider(): array
    {
        return [
            'home_to_sub' => [
                'source' => 'index.md',
                'link' => 'getting-started/configuration.md',
                'expected' => './getting-started/configuration',
            ],
            'same directory' => [
                'source' => 'docs/guide/installation.md',
                'link' => 'configuration.md',
                'expected' => 'configuration',
            ],
            'to parent' => [
                'source' => 'docs/guide/installation.md',
                'link' => '../overview.md',
                'expected' => '../overview',
            ],
            'to child' => [
                'source' => 'docs/guide/index.md',
                'link' => 'setup/install.md',
                'expected' => 'guide/setup/install',
            ],
            'between siblings' => [
                'source' => 'docs/guide/setup/install.md',
                'link' => '../deploy/cloud.md',
                'expected' => '../deploy/cloud',
            ],
            'from index to child' => [
                'source' => 'docs/index.md',
                'link' => 'guide/setup.md',
                'expected' => 'docs/guide/setup',
            ],
            'from child to index' => [
                'source' => 'docs/guide/setup.md',
                'link' => '../index.md',
                'expected' => '..',
            ],
            'with anchor' => [
                'source' => 'docs/guide.md',
                'link' => 'setup.md#installation',
                'expected' => 'setup#installation',
            ],
            'with query' => [
                'source' => 'docs/guide.md',
                'link' => 'setup.md?version=2',
                'expected' => 'setup?version=2',
            ],
            'absolute path' => [
                'source' => 'docs/deep/nested/guide.md',
                'link' => '/reference/api.md',
                'expected' => '../../../reference/api',
            ],
            'to current directory' => [
                'source' => 'docs/guide/install.md',
                'link' => './index.md',
                'expected' => '.',
            ],
            'from base index' => [
                'source' => 'overview.md',
                'link' => 'guide/setup.md',
                'expected' => 'guide/setup',
            ],
        ];
    }

    #[DataProvider('linkResolutionProvider')] public function test_link_resolution(string $source, string $link, string $expected): void
    {
        $this->assertLinkResolvesTo($source, $link, $expected);
    }

    public function test_external_links_remain_unchanged(): void
    {
        $links = [
            'https://example.com/doc.md',
            'http://example.com/guide',
            'ftp://files.example.com/doc.pdf',
            'mailto:user@example.com',
        ];

        foreach ($links as $link) {
            $content = "[Test]({$link})";
            $processed = $this->processor->processLinks($content, 'docs/test.md');
            $this->assertEquals($content, $processed);
        }
    }

    public function test_handles_multiple_links_in_content(): void
    {
        $content = <<<MD
# Test Document

[Link 1](setup.md)
[Link 2](../guide.md)
[External](https://example.com)
[Link 3](./local.md#section)
MD;

        $expected = <<<MD
# Test Document

[Link 1](setup)
[Link 2](../guide)
[External](https://example.com)
[Link 3](local#section)
MD;

        $processed = $this->processor->processLinks($content, 'docs/test.md');
        $this->assertEquals($expected, $processed);
    }
}
