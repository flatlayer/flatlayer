<?php

namespace Tests\Unit\Support;

use App\Support\Path;
use Tests\TestCase;

class PathTest extends TestCase
{
    /**
     * @dataProvider slugifyPathProvider
     */
    public function test_to_slug_correctly_formats_paths(string $input, string $expected)
    {
        $result = Path::toSlug($input);
        $this->assertEquals($expected, $result);
    }

    public static function slugifyPathProvider(): array
    {
        return [
            // Basic path normalization
            'basic path' => ['docs/getting-started.md', 'docs/getting-started'],
            'windows path' => ['docs\\windows\\path.md', 'docs/windows/path'],
            'leading slash' => ['/leading/slash.md', 'leading/slash'],
            'trailing slash' => ['trailing/slash.md/', 'trailing/slash'],
            'double slashes' => ['//double//slashes.md//', 'double/slashes'],
            'mixed slashes' => ['mixed\\slashes/path.md', 'mixed/slashes/path'],
            'special characters' => ['special@#$chars.md', 'special-chars'],
            'no extension' => ['no-extension', 'no-extension'],
            'spaces in path' => ['spaces in path.md', 'spaces-in-path'],

            // Index path normalization - these should all reduce to their parent path
            'root index' => ['index.md', ''],
            'nested index' => ['docs/getting-started/index.md', 'docs/getting-started'],
            'root level index' => ['docs/index.md', 'docs'],
            'deeply nested index' => ['deeply/nested/path/index.md', 'deeply/nested/path'],
            'multiple slashes with index' => ['multiple///slashes/index.md', 'multiple/slashes'],

            // Special cases
            'empty string' => ['', ''],
            'single slash' => ['/', ''],
            'dot directory' => ['./path.md', 'path'],
            'multiple extensions' => ['test.backup.md', 'test.backup'],
            'multiple dashes' => ['multiple---dashes.md', 'multiple-dashes'],
            'unicode characters' => ['über/café.md', 'uber/cafe'],
        ];
    }

    /**
     * @dataProvider isIndexProvider
     */
    public function test_is_index_correctly_identifies_index_files(string $path, bool $expected)
    {
        $result = Path::isIndex($path);
        $this->assertEquals($expected, $result);
    }

    public static function isIndexProvider(): array
    {
        return [
            'root index' => ['index.md', true],
            'nested index' => ['docs/index.md', true],
            'deeply nested index' => ['docs/section/subsection/index.md', true],
            'regular file' => ['regular-file.md', false],
            'nested regular file' => ['docs/regular-file.md', false],
            'file with index in name' => ['index-page.md', false],
            'file with index in path' => ['index/page.md', false],
        ];
    }

    /**
     * @dataProvider parentPathProvider
     */
    public function test_parent_returns_correct_parent_path(string $path, string $expected)
    {
        $result = Path::parent($path);
        $this->assertEquals($expected, $result);
    }

    public static function parentPathProvider(): array
    {
        return [
            'root path' => ['file.md', ''],
            'single level' => ['docs/file.md', 'docs'],
            'multiple levels' => ['docs/section/file.md', 'docs/section'],
            'deeply nested' => ['a/b/c/d/file.md', 'a/b/c/d'],
            'index file' => ['docs/section/index.md', 'docs'],
            'empty string' => ['', ''],
            'root index' => ['index.md', ''],
            'windows path' => ['docs\\section\\file.md', 'docs/section'],
        ];
    }

    /**
     * @dataProvider ancestorPathsProvider
     */
    public function test_ancestors_returns_correct_ancestor_paths(string $path, array $expected)
    {
        $result = Path::ancestors($path);
        $this->assertEquals($expected, $result);
    }

    public static function ancestorPathsProvider(): array
    {
        return [
            'single level' => [
                'docs/file.md',
                ['docs']
            ],
            'multiple levels' => [
                'docs/section/file.md',
                ['docs', 'docs/section']
            ],
            'deeply nested' => [
                'a/b/c/d/file.md',
                ['a', 'a/b', 'a/b/c', 'a/b/c/d']
            ],
            'index file' => [
                'docs/section/index.md',
                ['docs']
            ],
            'root file' => [
                'file.md',
                []
            ],
            'empty path' => [
                '',
                []
            ],
        ];
    }

    /**
     * @dataProvider siblingPathsProvider
     */
    public function test_siblings_returns_correct_sibling_paths(string $path, array $allPaths, array $expected)
    {
        $result = Path::siblings($path, $allPaths);
        sort($result);
        sort($expected);
        $this->assertEquals($expected, $result);
    }

    public static function siblingPathsProvider(): array
    {
        return [
            'root level siblings' => [
                'file1.md',
                ['file1.md', 'file2.md', 'file3.md', 'dir/file4.md'],
                ['file2.md', 'file3.md']
            ],
            'nested siblings' => [
                'dir/file1.md',
                ['dir/file1.md', 'dir/file2.md', 'dir/file3.md', 'dir/subdir/file4.md'],
                ['dir/file2.md', 'dir/file3.md']
            ],
            'no siblings' => [
                'unique/file.md',
                ['unique/file.md', 'other/file.md'],
                []
            ],
            'index file siblings' => [
                'dir/index.md',
                ['dir/index.md', 'dir/file1.md', 'dir/file2.md', 'dir/subdir/file3.md'],
                ['dir/file1.md', 'dir/file2.md']
            ],
            'empty directory' => [
                '',
                ['file1.md', 'file2.md', 'dir/file3.md'],
                ['file1.md', 'file2.md']
            ],
        ];
    }

    /**
     * @dataProvider childrenPathsProvider
     */
    public function test_children_returns_correct_child_paths(string $path, array $allPaths, array $expected)
    {
        $result = Path::children($path, $allPaths);
        sort($result);
        sort($expected);
        $this->assertEquals($expected, $result);
    }

    public static function childrenPathsProvider(): array
    {
        return [
            'root children' => [
                '',
                ['file1.md', 'file2.md', 'dir/file3.md', 'dir/subdir/file4.md'],
                ['file1.md', 'file2.md']
            ],
            'directory children' => [
                'dir',
                ['dir/file1.md', 'dir/file2.md', 'dir/subdir/file3.md', 'other/file4.md'],
                ['dir/file1.md', 'dir/file2.md']
            ],
            'nested directory children' => [
                'dir/subdir',
                ['dir/subdir/file1.md', 'dir/subdir/file2.md', 'dir/subdir/nested/file3.md'],
                ['dir/subdir/file1.md', 'dir/subdir/file2.md']
            ],
            'no children' => [
                'empty/dir',
                ['other/file1.md', 'different/file2.md'],
                []
            ],
            'complex hierarchy' => [
                'docs',
                [
                    'docs/intro.md',
                    'docs/getting-started.md',
                    'docs/advanced/topic1.md',
                    'docs/advanced/topic2.md',
                    'other/file.md'
                ],
                ['docs/intro.md', 'docs/getting-started.md']
            ],
        ];
    }

    public function test_path_operations_maintain_consistency()
    {
        // Use paths without extensions since we're testing logical path structure
        $paths = [
            'docs/getting-started',
            'docs/advanced/topic1',
            'docs/advanced/topic2',
            'docs/index',
            'blog/post1',
            'blog/post2',
        ];

        $testPath = 'docs/advanced/topic1';
        $parent = Path::parent($testPath);
        $ancestors = Path::ancestors($testPath);
        $this->assertEquals($parent, end($ancestors));

        $siblings = Path::siblings($testPath, $paths);
        $parentChildren = Path::children($parent, $paths);

        $mergedAndSorted = array_merge($siblings, [$testPath]);
        sort($mergedAndSorted);
        sort($parentChildren);

        $this->assertEquals($parentChildren, $mergedAndSorted);

        // Test that all paths are properly formatted
        $allPaths = array_merge(
            [$parent],
            $ancestors,
            $siblings,
            Path::children($testPath, $paths)
        );

        foreach ($allPaths as $path) {
            $this->assertEquals($path, Path::toSlug($path), "Path '$path' is not properly formatted");
        }
    }

    public function test_path_security()
    {
        $maliciousPaths = [
            '../etc/passwd',
            '..\\windows\\system32',
            'docs/../../../etc/passwd',
            'docs/../.././etc/passwd',
            'docs/%2e%2e/passwd',
            'docs/..%2fpasswd',
            'docs\\..\\passwd',
        ];

        foreach ($maliciousPaths as $path) {
            $result = Path::toSlug($path);
            // Ensure the result doesn't contain any directory traversal sequences
            $this->assertStringNotContainsString('..', $result);
            $this->assertStringNotContainsString('./', $result);
            $this->assertStringNotContainsString('%2e', $result);
            $this->assertStringNotContainsString('%2f', $result);
        }
    }
}
