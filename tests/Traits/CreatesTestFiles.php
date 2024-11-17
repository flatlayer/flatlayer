<?php

namespace Tests\Traits;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

trait CreatesTestFiles
{
    protected string $testContentPath = 'test-content';
    protected ImageManager $imageManager;
    protected Collection $createdFiles;
    protected Collection $createdDirectories;

    /**
     * Set up test files environment.
     */
    protected function setupTestFiles(?string $customPath = null): void
    {
        Storage::fake('local');
        $this->testContentPath = $customPath ?? $this->testContentPath;
        Storage::makeDirectory($this->testContentPath);
        $this->imageManager = new ImageManager(new Driver());
        $this->createdFiles = collect();
        $this->createdDirectories = collect();
    }

    /**
     * Create a markdown file with content and front matter.
     *
     * @param string $filename The name of the file to create
     * @param array $frontMatter Front matter data
     * @param string $content Main content of the file
     * @param string|null $title Optional title to override front matter
     * @param array $imagePaths Images to create for this file
     * @param bool $createImages Whether to create the referenced images
     * @throws \InvalidArgumentException If the filename is invalid
     * @return string Full path to created file
     */
    protected function createMarkdownFile(
        string $filename,
        array $frontMatter = [],
        string $content = '',
        ?string $title = null,
        array $imagePaths = [],
        bool $createImages = true
    ): string {
        // Validate filename
        if (!str_ends_with($filename, '.md')) {
            throw new \InvalidArgumentException('Filename must end with .md');
        }

        if (str_contains($filename, '../') || str_contains($filename, './')) {
            throw new \InvalidArgumentException('Path traversal not allowed in filename');
        }

        $path = $this->testContentPath . '/' . ltrim($filename, '/');
        $directory = dirname($path);

        // Create directory if it doesn't exist
        if (!Storage::exists($directory)) {
            Storage::makeDirectory($directory);
            $this->createdDirectories->push($directory);
        }

        $markdownContent = $this->generateMarkdownContent($frontMatter, $content, $title);

        if ($createImages) {
            // Create any images referenced in the front matter or content
            foreach ($imagePaths as $collection => $images) {
                $images = is_array($images) ? $images : [$images];
                foreach ($images as $image) {
                    $this->createImage($directory . '/' . $image);
                }
            }

            // Extract and create images from markdown content
            preg_match_all('/!\[([^\]]*)\]\(([^)]+)\)/', $content, $matches);
            foreach ($matches[2] as $imagePath) {
                if (!Str::startsWith($imagePath, ['http://', 'https://']) && !Storage::exists($directory . '/' . $imagePath)) {
                    $this->createImage($directory . '/' . $imagePath);
                }
            }
        }

        Storage::put($path, $markdownContent);
        $this->createdFiles->push($path);

        return Storage::path($path);
    }

    /**
     * Get relative path from storage root
     */
    protected function getRelativePath(string $path): string
    {
        $storagePath = Storage::path('');
        if (str_starts_with($path, $storagePath)) {
            return substr($path, strlen($storagePath));
        }
        return trim($path, '/');
    }

    /**
     * Create a post with complete front matter and optional embedded images
     */
    protected function createCompletePost(
        string $filename,
        string $title,
        array $tags = [],
        ?CarbonInterface $publishedAt = null,
        array $seo = [],
        array $additionalMeta = [],
        array $frontMatterImages = [],
        ?string $content = null
    ): string {
        $frontMatter = [
            'title' => $title,
            'type' => 'post',
            'tags' => $tags,
            'published_at' => $publishedAt?->format('Y-m-d H:i:s') ?? now()->format('Y-m-d H:i:s'),
            'meta' => array_merge([
                'author' => $this->faker->name,
                'seo' => array_merge([
                    'description' => $this->faker->sentence,
                    'keywords' => $this->faker->words(5),
                ], $seo),
            ], $additionalMeta),
        ];

        if (!empty($frontMatterImages)) {
            $frontMatter['images'] = $frontMatterImages;
        }

        // If no content provided, generate fake content with the title as an H1
        if ($content === null) {
            $content = "# {$title}\n\n" . $this->faker->paragraphs(3, true);
        }

        return $this->createMarkdownFile(
            $filename,
            $frontMatter,
            $content
        );
    }

    /**
     * Create a document with comprehensive front matter.
     */
    protected function createDocument(
        string $filename,
        string $title,
        string $difficulty = 'beginner',
        array $additionalMeta = [],
        array $images = [],
        ?CarbonInterface $publishedAt = null
    ): string {
        return $this->createMarkdownFile($filename, [
            'title' => $title,
            'type' => 'doc',
            'meta' => array_merge([
                'difficulty' => $difficulty,
                'category' => 'documentation',
                'version' => $this->faker->semver,
                'target_audience' => ['developers', 'users'],
                'reading_time' => $this->faker->numberBetween(5, 30),
            ], $additionalMeta),
            'published_at' => $publishedAt?->format('Y-m-d H:i:s'),
            'images' => $images,
        ]);
    }

    /**
     * Create a post with embedded images in the content
     *
     * @param string $filename The filename to create
     * @param string $title The post title
     * @param array|int $imageSpecs Either a count of images to create with defaults, or an array of image specifications
     * @param bool $includeExternalImages Whether to include external image references
     */
    protected function createPostWithEmbeddedImages(
        string $filename,
        string $title,
        array|int $imageSpecs = 2,
        bool $includeExternalImages = false
    ): string {
        $content = "# {$title}\n\n";

        // If imageSpecs is a number, create default specs
        if (is_int($imageSpecs)) {
            $specs = [];
            for ($i = 1; $i <= $imageSpecs; $i++) {
                $specs["image{$i}"] = [
                    'width' => 640,
                    'height' => 480,
                    'extension' => 'jpg',
                    'background' => '#' . substr(md5($i), 0, 6)
                ];
            }
        } else {
            $specs = $imageSpecs;
        }

        // Create the images
        $images = $this->createImageSet('images', $specs);

        // Add image references to content
        foreach ($images as $name => $path) {
            $content .= "![{$name}]({$path})\n\n";
            $content .= $this->faker->paragraph . "\n\n";
        }

        // Optionally add an external image reference
        if ($includeExternalImages) {
            $content .= "![External Image](https://example.com/image.jpg)\n\n";
            $content .= $this->faker->paragraph . "\n\n";
        }

        return $this->createCompletePost(
            $filename,
            $title,
            ['test', 'images'],
            now(),
            [],
            [],
            [], // No front matter images
            $content // Pass our custom content with embedded images
        );
    }

    /**
     * Create an image with specific dimensions and content.
     *
     * @param string $path Path where the image should be created
     * @param int $width Image width
     * @param int $height Image height
     * @param string $background Background color in hex format
     * @param string|null $text Optional text to add to the image
     * @param bool $createDirectories Whether to create intermediate directories
     * @throws \InvalidArgumentException If the path is invalid
     * @return string Full path to created image
     */
    protected function createImage(
        string $path,
        int $width = 640,
        int $height = 480,
        string $background = '#ff0000',
        ?string $text = null,
        bool $createDirectories = true
    ): string {
        // Validate image path
        if (str_contains($path, '../') || str_contains($path, './')) {
            throw new \InvalidArgumentException('Path traversal not allowed in image path');
        }

        $directory = dirname($path);
        if ($createDirectories && !Storage::exists($directory)) {
            Storage::makeDirectory($directory);
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

        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $imageData = match ($extension) {
            'png' => $image->toPng(),
            'webp' => $image->toWebp(),
            default => $image->toJpeg()
        };

        Storage::put($path, $imageData);
        $this->createdFiles->push($path);

        return Storage::path($path);
    }

    /**
     * Create multiple related images.
     */
    protected function createImageSet(
        string $directory,
        array $specifications
    ): array {
        // Always work with relative paths within test content directory
        $relativePath = trim($this->testContentPath . '/' . trim($directory, '/'), '/');

        // Ensure the directory exists
        if (!Storage::exists($relativePath)) {
            Storage::makeDirectory($relativePath);
            $this->createdDirectories->push($relativePath);
        }

        $createdImages = [];
        foreach ($specifications as $name => $spec) {
            $width = $spec['width'] ?? 640;
            $height = $spec['height'] ?? 480;
            $background = $spec['background'] ?? '#' . substr(md5(mt_rand()), 0, 6);
            $text = $spec['text'] ?? null;
            $extension = $spec['extension'] ?? 'jpg';

            $imagePath = $relativePath . "/{$name}.{$extension}";

            $this->createImage(
                $imagePath,
                $width,
                $height,
                $background,
                $text
            );

            // Store relative path from test content root
            $createdImages[$name] = trim(Str::after($imagePath, $this->testContentPath . '/'), '/');
        }

        return $createdImages;
    }

    /**
     * Create a hierarchical content structure.
     */
    protected function createHierarchicalContent(
        array $structure,
        string $baseType = 'doc',
        bool $createImages = true
    ): array {
        $createdFiles = [];
        $this->createStructureRecursively($structure, '', $baseType, $createdFiles, $createImages);
        return $createdFiles;
    }

    /**
     * Create a markdown file that will trigger specific behavior.
     *
     * @throws \InvalidArgumentException If the special case type is unknown
     */
    protected function createSpecialCaseFile(string $type, array $customizations = []): string
    {
        $content = match($type) {
            'published-true' => $this->createMarkdownFile(
                'special/published-true.md',
                ['title' => 'Test Published Post', 'published_at' => true],
                'This is a test post with published_at set to true.'
            ),
            'no-title-frontmatter' => $this->createMarkdownFile(
                'special/no-title.md',
                ['type' => 'post'],
                "# Markdown Title\nContent here."
            ),
            'multiple-headings' => $this->createMarkdownFile(
                'special/multiple-headings.md',
                ['type' => 'post'],
                "# Main Title\n## Subtitle\nContent"
            ),
            default => throw new \InvalidArgumentException("Unknown special case type: {$type}")
        };

        return $content;
    }

    /**
     * Generate markdown content from front matter and content.
     */
    private function generateMarkdownContent(array $frontMatter, string $content, ?string $title): string
    {
        $markdown = '';

        // Add front matter if present
        if (!empty($frontMatter)) {
            $markdown .= "---\n";
            $markdown .= $this->arrayToYaml($frontMatter);
            $markdown .= "---\n\n";
        }

        // Add title if provided
        if ($title) {
            $markdown .= "# {$title}\n\n";
        }

        // Add content
        $markdown .= $content;

        return $markdown;
    }

    /**
     * Convert an array to YAML format.
     */
    private function arrayToYaml(array $data, int $indent = 0): string
    {
        $yaml = '';
        $indentation = str_repeat(' ', $indent);

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $yaml .= "{$indentation}{$key}:\n";
                if (array_is_list($value)) {
                    foreach ($value as $item) {
                        $yaml .= "{$indentation}- " . (is_array($item) ? "\n" . $this->arrayToYaml($item, $indent + 4) : $item) . "\n";
                    }
                } else {
                    $yaml .= $this->arrayToYaml($value, $indent + 2);
                }
            } else {
                if (is_bool($value)) {
                    $formattedValue = $value ? 'true' : 'false';
                } else {
                    $formattedValue = is_string($value) ? "\"{$value}\"" : $value;
                }
                $yaml .= "{$indentation}{$key}: {$formattedValue}\n";
            }
        }

        return $yaml;
    }

    /**
     * Create nested content structure recursively.
     */
    private function createStructureRecursively(
        array $structure,
        string $basePath,
        string $type,
        array &$createdFiles,
        bool $createImages = true
    ): void {
        foreach ($structure as $name => $content) {
            $path = $basePath ? $basePath . '/' . $name : $name;

            if (is_array($content)) {
                // Handle index.md files specially
                if (str_ends_with($name, '.md')) {
                    // This is a file with content
                    $createdFiles[] = $this->createMarkdownFile(
                        $path,
                        array_merge($content, ['type' => $type]),
                        createImages: $createImages
                    );
                } else {
                    // This is a directory that might contain files
                    foreach ($content as $subName => $subContent) {
                        if (str_ends_with($subName, '.md')) {
                            // Create the file in this directory
                            $filePath = $path . '/' . $subName;
                            $createdFiles[] = $this->createMarkdownFile(
                                $filePath,
                                array_merge($subContent, ['type' => $type]),
                                createImages: $createImages
                            );

                            // Remove the processed file so we don't process it again
                            unset($content[$subName]);
                        }
                    }

                    // Process remaining subdirectories
                    if (!empty($content)) {
                        $this->createStructureRecursively($content, $path, $type, $createdFiles, $createImages);
                    }
                }
            } else {
                // Handle simple string content (treated as title)
                $createdFiles[] = $this->createMarkdownFile(
                    $path . '.md',
                    ['title' => $content, 'type' => $type],
                    createImages: $createImages
                );
            }
        }
    }

    /**
     * Clean up test files.
     */
    protected function tearDownTestFiles(): void
    {
        // Clean up files in reverse order (deeper files first)
        foreach ($this->createdFiles->reverse() as $file) {
            if (Storage::exists($file)) {
                Storage::delete($file);
            }
        }

        // Clean up directories in reverse order (deeper directories first)
        foreach ($this->createdDirectories->reverse() as $directory) {
            if (Storage::exists($directory)) {
                Storage::deleteDirectory($directory);
            }
        }

        // Clean up the main test content directory
        if (Storage::exists($this->testContentPath)) {
            Storage::deleteDirectory($this->testContentPath);
        }

        // Reset collections
        $this->createdFiles = collect();
        $this->createdDirectories = collect();
    }

    /**
     * Get the list of created files for inspection.
     */
    protected function getCreatedFiles(): array
    {
        return $this->createdFiles->toArray();
    }

    /**
     * Get the list of created directories for inspection.
     */
    protected function getCreatedDirectories(): array
    {
        return $this->createdDirectories->toArray();
    }

    /**
     * Get full path for a file in the test content directory.
     */
    protected function getTestPath(string $path): string
    {
        return Storage::path($this->testContentPath . '/' . ltrim($path, '/'));
    }
}
