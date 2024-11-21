<?php

namespace App\Services\Markdown;

use Illuminate\Support\Str;

class StructureExtractor
{
    /**
     * Extract a hierarchical structure from markdown content.
     *
     * @param string $content The markdown content
     * @param array $options Additional options
     *                      - max_depth: Maximum heading level to include (1-6, default: 6)
     *                      - min_depth: Minimum heading level to start from (1-6, default: 1)
     *                      - flatten: Return flat array instead of hierarchy (default: false)
     * @return array<int, array{
     *   title: string,
     *   level: int,
     *   normalized_level: int,
     *   anchor: string,
     *   position: array{line: int, offset: int},
     *   parent_anchor?: string,
     *   children?: array
     * }> The content structure
     */
    public function extract(string $content, array $options = []): array
    {
        if (empty($content)) {
            return [];
        }

        $maxDepth = min(6, max(1, $options['max_depth'] ?? 6));
        $minDepth = min($maxDepth, max(1, $options['min_depth'] ?? 1));
        $flatten = $options['flatten'] ?? false;

        $lines = explode("\n", $content);
        $structure = [];
        $flatStructure = [];
        $stack = [&$structure];
        $currentLevel = 0;
        $anchors = [];
        $position = 0;

        foreach ($lines as $lineNum => $line) {
            if (!$this->isHeading($line)) {
                $position += strlen($line) + 1;
                continue;
            }

            [$level, $title] = $this->parseHeading($line);

            // Skip if outside depth range
            if ($level < $minDepth || $level > $maxDepth) {
                $position += strlen($line) + 1;
                continue;
            }

            // Normalize the level based on min_depth
            $normalizedLevel = $level - $minDepth + 1;
            $anchor = $this->generateUniqueAnchor($title, $anchors);
            $heading = $this->createHeadingNode(
                title: $title,
                level: $level,
                normalizedLevel: $normalizedLevel,
                anchor: $anchor,
                lineNumber: $lineNum + 1,
                offset: $position
            );

            if ($flatten) {
                $this->addToFlatStructure($heading, $stack, $flatStructure);
            } else {
                $this->addToHierarchicalStructure($heading, $stack, $currentLevel, $normalizedLevel);
            }

            $position += strlen($line) + 1;
        }

        return $flatten ? $flatStructure : $structure;
    }

    /**
     * Check if a line is a markdown heading.
     */
    protected function isHeading(string $line): bool
    {
        return (bool) preg_match('/^#{1,6}\s+\S+/', $line);
    }

    /**
     * Parse a heading line into level and title.
     *
     * @return array{0: int, 1: string} [level, title]
     */
    protected function parseHeading(string $line): array
    {
        preg_match('/^(#{1,6})\s+(.+)$/', $line, $matches);
        return [
            strlen($matches[1]),
            trim($matches[2])
        ];
    }

    /**
     * Generate a unique anchor ID for a heading.
     */
    protected function generateUniqueAnchor(string $title, array &$anchors): string
    {
        $baseAnchor = Str::slug($title);
        $anchor = $baseAnchor;
        $counter = 1;

        while (isset($anchors[$anchor])) {
            $anchor = "{$baseAnchor}-{$counter}";
            $counter++;
        }

        $anchors[$anchor] = true;
        return $anchor;
    }

    /**
     * Create a heading node with metadata.
     *
     * @return array{
     *   title: string,
     *   level: int,
     *   normalized_level: int,
     *   anchor: string,
     *   position: array{line: int, offset: int},
     *   children: array
     * }
     */
    protected function createHeadingNode(
        string $title,
        int $level,
        int $normalizedLevel,
        string $anchor,
        int $lineNumber,
        int $offset
    ): array {
        return [
            'title' => $title,
            'level' => $level,
            'normalized_level' => $normalizedLevel,
            'anchor' => $anchor,
            'position' => [
                'line' => $lineNumber,
                'offset' => $offset,
            ],
            'children' => [],
        ];
    }

    /**
     * Add a heading to the flat structure.
     */
    protected function addToFlatStructure(array $heading, array &$stack, array &$flatStructure): void
    {
        $flatNode = $heading;
        unset($flatNode['children']);

        // Find the parent heading by looking for the closest heading with a lower level
        if (count($flatStructure) > 0) {
            for ($i = count($flatStructure) - 1; $i >= 0; $i--) {
                if ($flatStructure[$i]['level'] < $heading['level']) {
                    $flatNode['parent_anchor'] = $flatStructure[$i]['anchor'];
                    break;
                }
            }
        }

        $flatStructure[] = $flatNode;
    }

    /**
     * Add a heading to the hierarchical structure.
     */
    protected function addToHierarchicalStructure(
        array $heading,
        array &$stack,
        int &$currentLevel,
        int $normalizedLevel
    ): void {
        // Pop stack while current heading level is less than or equal to previous heading level
        while (count($stack) > 1) {
            $lastStackEntry = end($stack[count($stack) - 2]);
            if ($heading['level'] > $lastStackEntry['level']) {
                break;
            }
            array_pop($stack);
        }

        // Add the new heading to the last level in stack
        $lastIndex = count($stack) - 1;
        $stack[$lastIndex][] = $heading;
        $stack[] = &$stack[$lastIndex][count($stack[$lastIndex]) - 1]['children'];
    }
}
