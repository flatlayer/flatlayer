<?php

namespace Tests\Unit\Services\Markdown;

use App\Models\Entry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestFiles;

class MarkdownLinkProcessorTest extends TestCase
{
    use CreatesTestFiles, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupTestFiles();
    }

    /**
     * Test link processing from regular pages to index files.
     */
    public function test_links_from_regular_page_to_index_files()
    {
        $this->disk->put('docs/getting-started/installation.md', <<<'MD'
# Installation

- [Back to Getting Started](./index.md)
- [Back to Getting Started Alt](index.md)
- [Back to Docs](../index.md)
- [Root](../../index.md)
- [API Docs](../api/index.md)
- [API Reference](../api/reference/index.md)
MD
        );

        $model = Entry::createFromMarkdown($this->disk, 'docs/getting-started/installation.md', 'doc');

        // Links should maintain proper relative paths
        $this->assertStringContainsString('[Back to Getting Started](.)', $model->content);
        $this->assertStringContainsString('[Back to Getting Started Alt](.)', $model->content);
        $this->assertStringContainsString('[Back to Docs](..)', $model->content);
        $this->assertStringContainsString('[Root](../..)', $model->content);  // Fixed this line
        $this->assertStringContainsString('[API Docs](../api)', $model->content);
        $this->assertStringContainsString('[API Reference](../api/reference)', $model->content);
    }

    public function test_links_from_index_to_other_pages()
    {
        $this->disk->put('docs/getting-started/index.md', <<<'MD'
# Getting Started

- [Installation](installation.md)
- [Installation Alt](./installation.md)
- [Advanced Guide](advanced/guide.md)
- [API Reference](../api/reference.md)
- [Back to Docs](../index.md)
- [Root](../../index.md)
MD
        );

        $model = Entry::createFromMarkdown($this->disk, 'docs/getting-started/index.md', 'doc');

        // Links from index pages should maintain proper relative paths
        $this->assertStringContainsString('[Installation](./installation)', $model->content);
        $this->assertStringContainsString('[Installation Alt](./installation)', $model->content);
        $this->assertStringContainsString('[Advanced Guide](./advanced/guide)', $model->content);
        $this->assertStringContainsString('[API Reference](../api/reference)', $model->content);
        $this->assertStringContainsString('[Back to Docs](..)', $model->content);
        $this->assertStringContainsString('[Root](../..)', $model->content);
    }

    public function test_links_between_regular_pages()
    {
        $this->disk->put('docs/getting-started/installation.md', <<<'MD'
# Installation

- [Configuration](configuration.md)
- [Configuration Alt](./configuration.md)
- [Advanced Guide](advanced/guide.md)
- [Prerequisites](../prerequisites.md)
- [API Reference](../api/reference.md)
- [Troubleshooting](../troubleshooting/guide.md)
MD
        );

        $model = Entry::createFromMarkdown($this->disk, 'docs/getting-started/installation.md', 'doc');

        $this->assertStringContainsString('[Configuration](configuration)', $model->content);
        $this->assertStringContainsString('[Configuration Alt](configuration)', $model->content);
        $this->assertStringContainsString('[Advanced Guide](advanced/guide)', $model->content);
        $this->assertStringContainsString('[Prerequisites](../prerequisites)', $model->content);
        $this->assertStringContainsString('[API Reference](../api/reference)', $model->content);
        $this->assertStringContainsString('[Troubleshooting](../troubleshooting/guide)', $model->content);
    }

    public function test_edge_cases_and_special_characters()
    {
        $this->disk->put('docs/edge-cases/test.md', <<<'MD'
# Edge Cases

- [Multiple Dots](file.with.dots.md)
- [Query Params](page.md?version=2)
- [Anchor Links](page.md#section)
- [Both](page.md?version=2#section)
- [Spaces in Path](path/my page.md)
- [Special @Chars](path/@username.md)
- [Nested Traversal](./path/to/../page.md)
- [Double Slash](path//page.md)
- [Unicode](über/café.md)
MD
        );

        $model = Entry::createFromMarkdown($this->disk, 'docs/edge-cases/test.md', 'doc');

        $this->assertStringContainsString('[Multiple Dots](file-with-dots)', $model->content);
        $this->assertStringContainsString('[Query Params](page?version=2)', $model->content);
        $this->assertStringContainsString('[Anchor Links](page#section)', $model->content);
        $this->assertStringContainsString('[Both](page?version=2#section)', $model->content);
        $this->assertStringContainsString('[Spaces in Path](path/my-page)', $model->content);
        $this->assertStringContainsString('[Special @Chars](path/username)', $model->content);
        $this->assertStringContainsString('[Nested Traversal](path/page)', $model->content);
        $this->assertStringContainsString('[Double Slash](path/page)', $model->content);
        $this->assertStringContainsString('[Unicode](uber/cafe)', $model->content);
    }

    public function test_external_links_remain_unchanged()
    {
        $this->disk->put('docs/links.md', <<<'MD'
# External Links

- [Website](https://example.com)
- [HTTP](http://example.com/docs/guide.md)
- [FTP](ftp://example.com/guide.md)
- [Mail](mailto:user@example.com)
- [Absolute](/docs/guide.md)
MD
        );

        $model = Entry::createFromMarkdown($this->disk, 'docs/links.md', 'doc');

        $this->assertStringContainsString('[Website](https://example.com)', $model->content);
        $this->assertStringContainsString('[HTTP](http://example.com/docs/guide.md)', $model->content);
        $this->assertStringContainsString('[FTP](ftp://example.com/guide.md)', $model->content);
        $this->assertStringContainsString('[Mail](mailto:user@example.com)', $model->content);
        $this->assertStringContainsString('[Absolute](/docs/guide.md)', $model->content);
    }

    public function test_root_level_index_links()
    {
        $this->disk->put('index.md', <<<'MD'
# Root

- [Getting Started](docs/getting-started/index.md)
- [About](about.md)
- [Self Link](index.md)
- [Self Link Alt](./index.md)
MD
        );

        $model = Entry::createFromMarkdown($this->disk, 'index.md', 'doc');

        $this->assertStringContainsString('[Getting Started](./docs/getting-started)', $model->content);
        $this->assertStringContainsString('[About](./about)', $model->content);
        $this->assertStringContainsString('[Self Link](.)', $model->content);
        $this->assertStringContainsString('[Self Link Alt](.)', $model->content);
    }

    protected function tearDown(): void
    {
        $this->tearDownTestFiles();
        parent::tearDown();
    }
}
