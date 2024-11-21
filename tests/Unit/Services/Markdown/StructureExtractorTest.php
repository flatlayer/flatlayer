<?php

namespace Tests\Unit\Services\Markdown;

use App\Services\Markdown\StructureExtractor;
use PHPUnit\Framework\TestCase;

class StructureExtractorTest extends TestCase
{
    private StructureExtractor $extractor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extractor = new StructureExtractor();
    }

    public function test_extracts_basic_structure()
    {
        $content = <<<'MD'
# Main Title
Some content here
## Section 1
Content for section 1
## Section 2
Content for section 2
### Subsection 2.1
Deeper content
MD;

        $structure = $this->extractor->extract($content);

        $this->assertCount(1, $structure);
        $this->assertEquals('Main Title', $structure[0]['title']);
        $this->assertEquals(1, $structure[0]['level']);
        $this->assertEquals('main-title', $structure[0]['anchor']);
        $this->assertCount(2, $structure[0]['children']);

        $section1 = $structure[0]['children'][0];
        $this->assertEquals('Section 1', $section1['title']);
        $this->assertEquals(2, $section1['level']);
        $this->assertEquals('section-1', $section1['anchor']);
        $this->assertEmpty($section1['children']);

        $section2 = $structure[0]['children'][1];
        $this->assertEquals('Section 2', $section2['title']);
        $this->assertCount(1, $section2['children']);

        $subsection = $section2['children'][0];
        $this->assertEquals('Subsection 2.1', $subsection['title']);
        $this->assertEquals(3, $subsection['level']);
    }

    public function test_handles_empty_content()
    {
        $this->assertEmpty($this->extractor->extract(''));
        $this->assertEmpty($this->extractor->extract('Regular text without headings'));
    }

    public function test_respects_depth_limits()
    {
        $content = <<<'MD'
# Level 1
## Level 2
### Level 3
#### Level 4
##### Level 5
###### Level 6
MD;

        // Test max depth
        $structure = $this->extractor->extract($content, ['max_depth' => 3]);
        $this->assertCount(1, $structure);
        $level1 = $structure[0];
        $this->assertEquals('Level 1', $level1['title']);
        $level2 = $level1['children'][0];
        $this->assertEquals('Level 2', $level2['title']);
        $level3 = $level2['children'][0];
        $this->assertEquals('Level 3', $level3['title']);
        $this->assertEmpty($level3['children']);

        // Test min depth
        $structure = $this->extractor->extract($content, ['min_depth' => 2]);
        $this->assertCount(1, $structure);
        $this->assertEquals('Level 2', $structure[0]['title']);
        $this->assertEquals(1, $structure[0]['normalized_level']);
    }

    public function test_generates_unique_anchors()
    {
        $content = <<<'MD'
# Duplicate Title
## Duplicate Title
### Duplicate Title
MD;

        $structure = $this->extractor->extract($content);

        $this->assertEquals('duplicate-title', $structure[0]['anchor']);
        $this->assertEquals('duplicate-title-1', $structure[0]['children'][0]['anchor']);
        $this->assertEquals('duplicate-title-2', $structure[0]['children'][0]['children'][0]['anchor']);
    }

    public function test_handles_special_characters()
    {
        $content = <<<'MD'
# Title with @ Special * Characters!
## Section & More $ Characters %
## HTML <tags> & entities
## Emoji 👋 and Unicode ™ ®
MD;

        $structure = $this->extractor->extract($content);

        // Laravel's Str::slug preserves @ symbols but converts other special characters
        $this->assertEquals('title-with-at-special-characters', $structure[0]['anchor']);
        $this->assertEquals('section-more-characters', $structure[0]['children'][0]['anchor']);
        $this->assertEquals('html-tags-entities', $structure[0]['children'][1]['anchor']);
        $this->assertEquals('emoji-and-unicode', $structure[0]['children'][2]['anchor']);
    }

    public function test_tracks_positions()
    {
        $content = "# First Title\nSome content\n## Second Title";

        // Let's break down the offset calculation:
        $firstLineLength = strlen("# First Title");  // 12 chars
        $firstLineEnd = 1;  // \n character
        $secondLineLength = strlen("Some content");  // 12 chars
        $secondLineEnd = 1;  // \n character
        $expectedOffset = $firstLineLength + $firstLineEnd + $secondLineLength + $secondLineEnd;  // 27

        $structure = $this->extractor->extract($content);

        $this->assertEquals(1, $structure[0]['position']['line']);
        $this->assertEquals(0, $structure[0]['position']['offset']);
        $this->assertEquals(3, $structure[0]['children'][0]['position']['line']);
        $this->assertEquals($expectedOffset, $structure[0]['children'][0]['position']['offset']);

        // Also verify with multiple headings and longer content
        $multiLineContent = <<<'MD'
# First Title
Some content
that spans
multiple lines
## Second Title
More content
### Third Title
MD;

        $structure = $this->extractor->extract($multiLineContent);

        // Verify each heading is on the expected line
        $this->assertEquals(1, $structure[0]['position']['line']);
        $this->assertEquals(5, $structure[0]['children'][0]['position']['line']);
        $this->assertEquals(7, $structure[0]['children'][0]['children'][0]['position']['line']);
    }

    public function test_creates_flat_structure()
    {
        $content = <<<'MD'
# Main Title
## Section 1
### Subsection 1.1
## Section 2
### Subsection 2.1
MD;

        $structure = $this->extractor->extract($content, ['flatten' => true]);

        $this->assertCount(5, $structure);

        // Main Title
        $this->assertEquals('Main Title', $structure[0]['title']);
        $this->assertEquals(1, $structure[0]['level']);
        $this->assertArrayNotHasKey('parent_anchor', $structure[0]);

        // Section 1
        $this->assertEquals('Section 1', $structure[1]['title']);
        $this->assertEquals(2, $structure[1]['level']);
        $this->assertEquals('main-title', $structure[1]['parent_anchor']);

        // Subsection 1.1
        $this->assertEquals('Subsection 1.1', $structure[2]['title']);
        $this->assertEquals(3, $structure[2]['level']);
        $this->assertEquals('section-1', $structure[2]['parent_anchor']);

        // Section 2
        $this->assertEquals('Section 2', $structure[3]['title']);
        $this->assertEquals(2, $structure[3]['level']);
        $this->assertEquals('main-title', $structure[3]['parent_anchor']);

        // Subsection 2.1
        $this->assertEquals('Subsection 2.1', $structure[4]['title']);
        $this->assertEquals(3, $structure[4]['level']);
        $this->assertEquals('section-2', $structure[4]['parent_anchor']);

        // Test more complex nesting
        $complexContent = <<<'MD'
# Title
### Skip Level
## Back Up
#### Deep
## Another Section
MD;

        $complexStructure = $this->extractor->extract($complexContent, ['flatten' => true]);

        $this->assertCount(5, $complexStructure);
        $this->assertEquals('Title', $complexStructure[0]['title']);
        $this->assertArrayNotHasKey('parent_anchor', $complexStructure[0]);

        $this->assertEquals('Skip Level', $complexStructure[1]['title']);
        $this->assertEquals('title', $complexStructure[1]['parent_anchor']);

        $this->assertEquals('Back Up', $complexStructure[2]['title']);
        $this->assertEquals('title', $complexStructure[2]['parent_anchor']);

        $this->assertEquals('Deep', $complexStructure[3]['title']);
        $this->assertEquals('back-up', $complexStructure[3]['parent_anchor']);

        $this->assertEquals('Another Section', $complexStructure[4]['title']);
        $this->assertEquals('title', $complexStructure[4]['parent_anchor']);
    }

    public function test_handles_non_sequential_heading_levels()
    {
        $content = <<<'MD'
# Title
### Direct to H3
## Back to H2
MD;

        $structure = $this->extractor->extract($content);

        $this->assertCount(1, $structure);
        $title = $structure[0];
        $this->assertEquals('Title', $title['title']);

        // Both H3 and H2 should be at the same level under H1
        $this->assertCount(2, $title['children']);
        $this->assertEquals('Direct to H3', $title['children'][0]['title']);
        $this->assertEquals('Back to H2', $title['children'][1]['title']);
    }

    public function test_ignores_malformed_headings()
    {
        $content = <<<'MD'
#Not a heading
# Valid Heading
##Also not a heading
## Valid Subheading
MD;

        $structure = $this->extractor->extract($content);

        $this->assertCount(1, $structure);
        $this->assertEquals('Valid Heading', $structure[0]['title']);
        $this->assertCount(1, $structure[0]['children']);
        $this->assertEquals('Valid Subheading', $structure[0]['children'][0]['title']);
    }

    public function test_normalizes_heading_levels_with_min_depth()
    {
        $content = <<<'MD'
## Start at H2
### H3 Content
#### H4 Content
MD;

        $structure = $this->extractor->extract($content, ['min_depth' => 2]);

        $this->assertCount(1, $structure);
        $root = $structure[0];
        $this->assertEquals('Start at H2', $root['title']);
        $this->assertEquals(2, $root['level']); // Original level
        $this->assertEquals(1, $root['normalized_level']); // Normalized to depth 1

        $h3 = $root['children'][0];
        $this->assertEquals(3, $h3['level']);
        $this->assertEquals(2, $h3['normalized_level']);
    }
}
