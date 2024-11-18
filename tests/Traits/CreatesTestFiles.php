<?php

namespace Tests\Traits;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

trait CreatesTestFiles
{
    protected Filesystem $disk;

    protected ImageManager $imageManager;

    protected Collection $createdFiles;

    protected Collection $createdDirectories;

    /**
     * Set up test files environment.
     */
    protected function setupTestFiles(string $diskName = 'content'): void
    {
        Storage::fake($diskName);
        $this->disk = Storage::disk($diskName);
        $this->imageManager = new ImageManager(new Driver);
        $this->createdFiles = collect();
        $this->createdDirectories = collect();
    }

    /**
     * Generate markdown content with front matter.
     */
    protected function generateMarkdownContent(array $frontMatter = [], string $content = '', ?string $title = null): string
    {
        $frontMatterContent = '';
        if (! empty($frontMatter) || $title) {
            $frontMatterContent = "---\n";
            if ($title) {
                $frontMatterContent .= "title: {$title}\n";
            }
            foreach ($frontMatter as $key => $value) {
                $frontMatterContent .= "{$key}: ".$this->formatYamlValue($value)."\n";
            }
            $frontMatterContent .= "---\n\n";
        }

        if ($title && ! str_contains($content, '# ')) {
            $content = "# {$title}\n\n".$content;
        }

        return $frontMatterContent.$content;
    }

    /**
     * Format a value for YAML front matter.
     */
    protected function formatYamlValue(mixed $value): string
    {
        if (is_array($value)) {
            if (array_is_list($value)) {
                return '['.implode(', ', array_map([$this, 'formatYamlValue'], $value)).']';
            }
            $formatted = "{\n";
            foreach ($value as $k => $v) {
                $formatted .= "  {$k}: ".$this->formatYamlValue($v).",\n";
            }

            return rtrim($formatted, ",\n")."\n}";
        }

        if (is_string($value) && str_contains($value, ' ')) {
            return '"'.$value.'"';
        }

        return (string) $value;
    }

    /**
     * Create a markdown file with content and front matter.
     */
    protected function createMarkdownFile(
        string $relativePath,
        array $frontMatter = [],
        string $content = '',
        ?string $title = null,
        array $imagePaths = [],
        bool $createImages = true
    ): void {
        if (! str_ends_with($relativePath, '.md')) {
            throw new \InvalidArgumentException('Filename must end with .md');
        }

        if (str_contains($relativePath, '../') || str_contains($relativePath, './')) {
            throw new \InvalidArgumentException('Path traversal not allowed');
        }

        $directory = dirname($relativePath);
        if ($directory !== '.') {
            $this->disk->makeDirectory($directory);
            $this->createdDirectories->push($directory);
        }

        $markdownContent = $this->generateMarkdownContent($frontMatter, $content, $title);

        if ($createImages && ! empty($imagePaths)) {
            $this->createImagesForMarkdown($directory, $imagePaths, $content);
        }

        $this->disk->put($relativePath, $markdownContent);
        $this->createdFiles->push($relativePath);
    }

    /**
     * Create test files for markdown model tests - maintains compatibility with existing tests.
     */
    protected function createMarkdownModelTestFiles(): void
    {
        // Create base post with front matter and images
        $this->createBasicPostWithImage();

        // Create files for sync testing
        $this->createSyncTestFiles();

        // Create special cases
        $this->createSpecialCaseFiles();

        // Create hierarchical documentation
        $this->createDocumentationStructure();

        // Create files with multiple image collections
        $this->createMultiImageCollectionPost();

        // Create index files
        $this->createIndexFiles();

        // Create image handling cases
        $this->createImageHandlingCases();

        // Create additional test cases
        $this->createAdditionalTestCases();
    }

    /**
     * Create a basic post with front matter and image.
     */
    protected function createBasicPostWithImage(): void
    {
        $this->disk->makeDirectory('images');

        // Create the featured image
        $this->createImage('images/featured.jpg', 1200, 630);

        $content = <<<'MD'
---
title: Test Basic Markdown
type: post
tags: [tag1, tag2]
published_at: 2024-01-01
description: Test description
keywords: [test, markdown]
author: John Doe
images:
  featured: images/featured.jpg
---

# Test Basic Markdown

Test content here
MD;

        $this->disk->put('test-basic.md', $content);
    }

    /**
     * Create image handling test cases.
     */
    protected function createImageHandlingCases(): void
    {
        $this->disk->makeDirectory('images');

        // Create test images
        $imageSpecs = [
            'square.jpg' => [800, 800],
            'portrait.jpg' => [600, 800],
            'landscape.jpg' => [800, 600],
            'nested/external.jpg' => [400, 300],
        ];

        foreach ($imageSpecs as $name => [$width, $height]) {
            $this->createImage("images/{$name}", $width, $height);
        }

        // Pure markdown images
        $this->createMarkdownFile(
            'images/inline-images.md',
            ['type' => 'post'],
            <<<'MD'
# Images in markdown

![First Image](images/square.jpg)

![Second Image](images/portrait.jpg)

![Third Image](images/landscape.jpg)
MD
        );

        // Mixed frontmatter and inline images
        $this->createMarkdownFile(
            'images/mixed-references.md',
            [
                'type' => 'post',
                'images' => [
                    'featured' => 'images/square.jpg',
                    'gallery' => ['images/portrait.jpg', 'images/landscape.jpg'],
                ],
            ],
            <<<'MD'
# Mixed Image References

Featured image: ![Featured](images/square.jpg)
Gallery image: ![Gallery](images/portrait.jpg)
External image: ![External](https://example.com/image.jpg)
MD
        );

        // Relative paths
        $this->disk->makeDirectory('images/nested');
        $this->createMarkdownFile(
            'images/nested/relative-paths.md',
            ['type' => 'post'],
            <<<'MD'
# Images with relative paths

Up one level: ![Up](../square.jpg)
Same level: ![Same](./external.jpg)
Absolute path: ![Root](/images/landscape.jpg)
MD
        );
    }

    /**
     * Create files for sync testing.
     */
    protected function createSyncTestFiles(): void
    {
        // Initial version
        $initial = <<<'MD'
---
title: Initial Title
type: post
meta:
  version: 1.0.0
---
Initial content
MD;

        $this->disk->put('test-sync.md', $initial);

        // Store updated content for later use in tests
        $updated = <<<'MD'
---
title: Updated Title
type: post
meta:
  version: 1.0.1
---
Updated content
MD;

        $this->disk->put('test-sync-updated.md', $updated);
    }

    /**
     * Create files for special cases.
     */
    protected function createSpecialCaseFiles(): void
    {
        $this->disk->makeDirectory('special');

        // Published true case
        $publishedTrue = <<<'MD'
---
type: post
published_at: true
---
Test content
MD;
        $this->disk->put('special/published-true.md', $publishedTrue);

        // No title in front matter
        $noTitle = <<<'MD'
---
type: post
---
# Markdown Title
Content here.
MD;
        $this->disk->put('special/no-title.md', $noTitle);

        // Multiple headings
        $multipleHeadings = <<<'MD'
---
type: post
---
# Main Title
## Subtitle
Content
MD;
        $this->disk->put('special/multiple-headings.md', $multipleHeadings);
    }

    /**
     * Create hierarchical documentation structure.
     */
    protected function createDocumentationStructure(): void
    {
        $this->disk->makeDirectory('docs/getting-started');

        // Root index
        $this->disk->put('docs/index.md', <<<'MD'
---
title: Documentation
type: doc
meta:
  section: root
  nav_order: 1
---
Documentation content
MD);

        // Getting started section
        $this->disk->put('docs/getting-started/index.md', <<<'MD'
---
title: Getting Started
type: doc
meta:
  section: tutorial
  nav_order: 2
---
Getting started content
MD);

        // Installation guide
        $this->disk->put('docs/getting-started/installation.md', <<<'MD'
---
title: Installation
type: doc
meta:
  difficulty: beginner
  time_required: 15
  prerequisites: [git, php]
---
Installation content
MD);
    }

    /**
     * Create a post with multiple image collections.
     */
    protected function createMultiImageCollectionPost(): void
    {
        $this->disk->makeDirectory('images');

        // Create various images
        $imageSpecs = [
            'featured' => ['width' => 1200, 'height' => 630, 'extension' => 'jpg'],
            'gallery1' => ['width' => 800, 'height' => 600, 'extension' => 'jpg'],
            'gallery2' => ['width' => 800, 'height' => 600, 'extension' => 'jpg'],
            'thumb1' => ['width' => 150, 'height' => 150, 'extension' => 'png'],
            'thumb2' => ['width' => 150, 'height' => 150, 'extension' => 'png'],
        ];

        foreach ($imageSpecs as $name => $spec) {
            $this->createImage(
                "images/{$name}.{$spec['extension']}",
                $spec['width'],
                $spec['height']
            );
        }

        $content = <<<'MD'
---
type: post
title: Test Multiple Images
images:
  featured: images/featured.jpg
  gallery:
    - images/gallery1.jpg
    - images/gallery2.jpg
  thumbnails:
    - images/thumb1.png
    - images/thumb2.png
---

# Test Multiple Images

Content with multiple image collections
MD;

        $this->disk->put('test-multiple-images.md', $content);
    }

    /**
     * Create index files.
     */
    protected function createIndexFiles(): void
    {
        $this->disk->makeDirectory('section');

        // Section index
        $this->disk->put('section/index.md', <<<'MD'
---
type: doc
title: Section Index
---
# Section Index Content
MD);

        // Root index
        $this->disk->put('index.md', <<<'MD'
---
type: doc
title: Root Index
---
# Root Index Content
MD);
    }

    /**
     * Create additional test cases for enhanced testing.
     */
    protected function createAdditionalTestCases(): void
    {
        // Date handling cases
        $this->createDateHandlingCases();

        // Complex meta cases
        $this->createComplexMetaCases();

        // Invalid cases
        $this->createInvalidCases();
    }

    /**
     * Create date handling test cases.
     */
    protected function createDateHandlingCases(): void
    {
        $this->disk->makeDirectory('dates');

        // Various date formats
        $this->createMarkdownFile(
            'dates/iso-format.md',
            [
                'type' => 'post',
                'published_at' => '2024-01-01T10:00:00Z',
            ],
            'ISO date format test'
        );

        $this->createMarkdownFile(
            'dates/date-only.md',
            [
                'type' => 'post',
                'published_at' => '2024-01-01',
            ],
            'Date only format test'
        );

        $this->createMarkdownFile(
            'dates/published-true.md',
            [
                'type' => 'post',
                'published_at' => true,
            ],
            'Published true test'
        );
    }

    /**
     * Create complex meta test cases.
     */
    protected function createComplexMetaCases(): void
    {
        $this->disk->makeDirectory('meta');

        // Special characters in meta
        $this->createMarkdownFile(
            'meta/special-chars.md',
            [
                'type' => 'post',
                'meta' => [
                    'quotes' => 'String with "quotes"',
                    'multiline' => "Line 1\nLine 2",
                    'symbols' => '$@#%',
                ],
            ],
            'Content with special characters'
        );

        // Complex nested meta
        $this->createMarkdownFile(
            'meta/complex-meta.md',
            [
                'type' => 'post',
                'meta' => [
                    'level1' => [
                        'level2' => [
                            'level3' => 'deep value',
                        ],
                        'array' => [1, 2, 3],
                    ],
                ],
            ],
            'Testing complex metadata'
        );
    }

    /**
     * Create invalid cases for error testing.
     */
    protected function createInvalidCases(): void
    {
        $this->disk->makeDirectory('invalid');

        // Invalid YAML
        $this->disk->put('invalid/bad-yaml.md', <<<'MD'
---
title: "Unclosed quote
type: post
---
Content
MD);

        // Invalid date
        $this->disk->put('invalid/bad-date.md', <<<'MD'
---
type: post
published_at: not-a-date
---
Content
MD);

        // Missing required fields
        $this->disk->put('invalid/missing-type.md', <<<'MD'
---
title: No Type
---
Content
MD);
    }

    /**
     * Create images referenced in markdown content.
     */
    protected function createImagesForMarkdown(string $directory, array $imagePaths, string $content): void
    {
        // Create directory-based images
        foreach ($imagePaths as $collection => $images) {
            $images = is_array($images) ? $images : [$images];
            foreach ($images as $image) {
                $imagePath = trim($directory.'/'.$image, '/');
                if (! $this->disk->exists($imagePath)) {
                    $this->createImage($imagePath);
                }
            }
        }

        // Create images referenced in markdown content
        preg_match_all('/!\[([^\]]*)\]\(([^)]+)\)/', $content, $matches);
        foreach ($matches[2] as $imagePath) {
            if (! Str::startsWith($imagePath, ['http://', 'https://'])) {
                $fullPath = trim($directory.'/'.$imagePath, '/');
                if (! $this->disk->exists($fullPath)) {
                    $this->createImage($fullPath);
                }
            }
        }
    }

    /**
     * Create an image with specific dimensions.
     */
    protected function createImage(
        string $relativePath,
        int $width = 640,
        int $height = 480,
        string $background = '#ff0000',
        ?string $text = null
    ): void {
        if (str_contains($relativePath, '../') || str_contains($relativePath, './')) {
            throw new \InvalidArgumentException('Path traversal not allowed');
        }

        $directory = dirname($relativePath);
        if ($directory !== '.') {
            $this->disk->makeDirectory($directory);
            $this->createdDirectories->push($directory);
        }

        $image = $this->imageManager->create($width, $height);
        $image->fill($background);

        if ($text) {
            $image->text($text, $width / 2, $height / 2, function ($font) {
                $font->color('#ffffff');
                $font->align('center');
                $font->valign('middle');
                $font->size(24);
            });
        }

        $extension = pathinfo($relativePath, PATHINFO_EXTENSION);
        $imageData = match ($extension) {
            'png' => $image->toPng(),
            'webp' => $image->toWebp(),
            default => $image->toJpeg()
        };

        $this->disk->put($relativePath, $imageData);
        $this->createdFiles->push($relativePath);
    }

    /**
     * Clean up test files.
     */
    protected function tearDownTestFiles(): void
    {
        if (isset($this->disk)) {
            // Delete files in reverse order (deepest first)
            foreach ($this->createdFiles->reverse() as $file) {
                $this->disk->delete($file);
            }

            // Delete directories in reverse order (deepest first)
            foreach ($this->createdDirectories->reverse() as $directory) {
                $this->disk->deleteDirectory($directory);
            }
        }
    }
}
