<?php

namespace Tests\Unit;

use App\Models\Entry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchableTest extends TestCase
{
    use RefreshDatabase;

    public function test_update_search_vector()
    {
        $entry = Entry::factory()->create([
            'title' => 'Test Title',
            'content' => 'Test Content',
            'type' => 'post',
        ]);

        $this->assertNotEmpty($entry->embedding);
        $this->assertCount(768, $entry->embedding->toArray());
    }

    public function test_search_without_reranking()
    {
        $first = Entry::factory()->create([
            'title' => 'First document',
            'content' => 'This is the first test document',
            'type' => 'post',
        ]);
        $second = Entry::factory()->create([
            'title' => 'Second document',
            'content' => 'This is the second test document',
            'type' => 'post',
        ]);

        $results = Entry::search('test document', 2, false);

        $this->assertCount(2, $results);
        $this->assertTrue(isset($results[0]->similarity), 'First result should have a similarity attribute');
        $this->assertTrue(isset($results[1]->similarity), 'Second result should have a similarity attribute');
        $this->assertNotEquals($results[0]->similarity, $results[1]->similarity);

        // Check order and content of results
        $this->assertEquals($first->id, $results[0]->id);
        $this->assertEquals($second->id, $results[1]->id);
        $this->assertTrue($results->contains($first));
        $this->assertTrue($results->contains($second));
    }

    public function test_search_with_reranking()
    {
        Entry::factory()->create([
            'title' => 'First',
            'content' => 'This is a test document',
            'type' => 'post',
        ]);
        Entry::factory()->create([
            'title' => 'Second',
            'content' => 'This is an actual real document',
            'type' => 'post',
        ]);

        $results = Entry::search('test document', 2, true);

        $this->assertEquals(2, $results->count());
        $this->assertEquals('First', $results[0]->title);
        $this->assertEquals('Second', $results[1]->title);

        // Check relevance scores
        $this->assertGreaterThanOrEqual(0.3, $results[0]->relevance, 'First result should have higher relevance due to more overlapping words');
        $this->assertGreaterThanOrEqual(0.1, $results[1]->relevance, 'Second result should have lower but positive relevance');
        $this->assertGreaterThan($results[1]->relevance, $results[0]->relevance, 'First result should be more relevant than the second');
    }

    public function test_strip_mdx_components()
    {
        $entry = new class extends Entry
        {
            public function stripMdxComponents(string $content): string
            {
                return parent::stripMdxComponents($content);
            }
        };

        $testCases = [
            [
                'input' => '<ComponentName prop={{"foo": "bar"}}>Internal Content</ComponentName>',
                'expected' => 'Internal Content',
            ],
            [
                'input' => 'Text before <Component1>Content 1</Component1> text between <Component2 prop="value">Content 2</Component2> text after',
                'expected' => 'Text before Content 1 text between Content 2 text after',
            ],
            [
                'input' => 'Self-closing tag <SelfClosingComponent /> should be removed',
                'expected' => 'Self-closing tag should be removed',
            ],
            [
                'input' => 'Nested components <Outer><Inner>Nested Content</Inner></Outer>',
                'expected' => 'Nested components Nested Content',
            ],
            [
                'input' => 'Multiple self-closing tags <Tag1 /><Tag2 /><Tag3 /> in content',
                'expected' => 'Multiple self-closing tags in content',
            ],
        ];

        foreach ($testCases as $index => $case) {
            $result = $entry->stripMdxComponents($case['input']);
            $this->assertEquals($case['expected'], $result, "Test case {$index} failed");
        }
    }

    public function test_to_searchable_text_strips_mdx_components()
    {
        $entry = Entry::factory()->create([
            'title' => 'Test Title',
            'content' => 'This is <Component1>searchable content</Component1> with <Component2 prop="value" />MDX components.',
            'type' => 'post',
        ]);

        $searchableText = $entry->toSearchableText();

        $this->assertStringContainsString('Test Title', $searchableText);
        $this->assertStringContainsString('This is searchable content with MDX components.', $searchableText);
        $this->assertStringNotContainsString('<Component1>', $searchableText);
        $this->assertStringNotContainsString('<Component2', $searchableText);
    }

    public function test_strip_complex_mdx_components()
    {
        $entry = new class extends Entry
        {
            public function stripMdxComponents(string $content): string
            {
                return parent::stripMdxComponents($content);
            }
        };

        $testCases = [
            [
                'input' => '<Component prop={{"key": "value", "nested": {"foo": "bar"}}}>Complex JSON prop</Component>',
                'expected' => 'Complex JSON prop',
            ],
            [
                'input' => '<Component prop={42}>Numeric prop</Component>',
                'expected' => 'Numeric prop',
            ],
            [
                'input' => '<Component prop="Simple string">String prop</Component>',
                'expected' => 'String prop',
            ],
            [
                'input' => 'Text with <inlineCode>code {with} braces</inlineCode> and <a href="https://example.com">link</a>',
                'expected' => 'Text with code {with} braces and link',
            ],
            [
                'input' => "<Component1 prop1=\"value1\">\n  <Component2 prop2={{\"key\": \"value\"}}>\n    Nested component\n  </Component2>\n</Component1>",
                'expected' => 'Nested component',
            ],
            [
                'input' => '<Component prop={true}>Boolean prop</Component>',
                'expected' => 'Boolean prop',
            ],
            [
                'input' => '<Component prop={null}>Null prop</Component>',
                'expected' => 'Null prop',
            ],
            [
                'input' => '<Component prop={[1, 2, 3]}>Array prop</Component>',
                'expected' => 'Array prop',
            ],
            [
                'input' => "<Component>\n  Multi-line\n  content\n</Component>",
                'expected' => 'Multi-line content',
            ],
        ];

        foreach ($testCases as $index => $case) {
            $result = $entry->stripMdxComponents($case['input']);
            $this->assertEquals($case['expected'], $result, "Complex test case {$index} failed");
        }
    }
}
