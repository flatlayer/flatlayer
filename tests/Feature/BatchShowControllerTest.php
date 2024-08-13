<?php

namespace Tests\Feature;

use App\Models\Entry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BatchShowControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_can_retrieve_multiple_entries_by_slugs()
    {
        $post1 = Entry::factory()->create([
            'type' => 'post',
            'title' => 'First Post',
            'slug' => 'first-post',
            'content' => 'This is the first post.',
        ]);

        $post2 = Entry::factory()->create([
            'type' => 'post',
            'title' => 'Second Post',
            'slug' => 'second-post',
            'content' => 'This is the second post.',
        ]);

        $response = $this->getJson("/entry/batch/post?slugs={$post1->slug},{$post2->slug}");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.title', 'First Post')
            ->assertJsonPath('data.1.title', 'Second Post');
    }

    public function test_batch_show_respects_fields_parameter()
    {
        $post1 = Entry::factory()->create([
            'type' => 'post',
            'title' => 'First Post',
            'slug' => 'first-post',
            'content' => 'This is the first post.',
        ]);

        $post2 = Entry::factory()->create([
            'type' => 'post',
            'title' => 'Second Post',
            'slug' => 'second-post',
            'content' => 'This is the second post.',
        ]);

        $fields = json_encode(['title', 'slug']);
        $response = $this->getJson("/entry/batch/post?slugs={$post1->slug},{$post2->slug}&fields={$fields}");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [
                    ['title', 'slug'],
                    ['title', 'slug'],
                ]
            ])
            ->assertJsonMissing(['content']);
    }

    public function test_batch_show_returns_404_for_non_existent_entries()
    {
        $post = Entry::factory()->create([
            'type' => 'post',
            'slug' => 'existing-post',
        ]);

        $response = $this->getJson("/entry/batch/batch?slugs={$post->slug},non-existent-post");

        $response->assertStatus(404)
            ->assertJson(['error' => 'No items found for the specified type and slugs']);
    }

    public function test_batch_show_returns_404_for_mismatched_type()
    {
        $post = Entry::factory()->create([
            'type' => 'post',
            'slug' => 'test-post',
        ]);

        $response = $this->getJson("/entry/batch/document?slugs={$post->slug}");

        $response->assertStatus(404)
            ->assertJson(['error' => 'No items found for the specified type and slugs']);
    }

    public function test_batch_show_handles_empty_slugs_string()
    {
        $response = $this->getJson("/entry/batch/post?slugs=");

        $response->assertStatus(400)
            ->assertJsonStructure(['error']);
    }

    public function test_batch_show_handles_large_number_of_slugs()
    {
        $slugs = [];
        for ($i = 0; $i < 100; $i++) {
            $post = Entry::factory()->create([
                'type' => 'post',
                'slug' => "post-{$i}",
            ]);
            $slugs[] = $post->slug;
        }

        $slugString = implode(',', $slugs);
        $response = $this->getJson("/entry/batch/post?slugs={$slugString}");

        $response->assertStatus(200)
            ->assertJsonCount(100, 'data');
    }

    public function test_batch_show_maintains_order_of_requested_slugs()
    {
        $post1 = Entry::factory()->create(['type' => 'post', 'slug' => 'slug-1']);
        $post2 = Entry::factory()->create(['type' => 'post', 'slug' => 'slug-2']);
        $post3 = Entry::factory()->create(['type' => 'post', 'slug' => 'slug-3']);

        $response = $this->getJson("/entry/batch/post?slugs=slug-2,slug-3,slug-1");

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.slug', 'slug-2')
            ->assertJsonPath('data.1.slug', 'slug-3')
            ->assertJsonPath('data.2.slug', 'slug-1');
    }

    public function test_batch_show_handles_duplicate_slugs()
    {
        $post = Entry::factory()->create([
            'type' => 'post',
            'title' => 'Test Post',
            'slug' => 'test-post',
            'content' => 'This is a test post.',
        ]);

        $response = $this->getJson("/entry/batch/post?slugs={$post->slug},{$post->slug}");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Test Post');
    }

    public function test_batch_show_handles_slugs_with_commas()
    {
        $post1 = Entry::factory()->create([
            'type' => 'post',
            'title' => 'Post with, comma',
            'slug' => 'post-with-comma',
            'content' => 'This post has a comma in the title.',
        ]);

        $post2 = Entry::factory()->create([
            'type' => 'post',
            'title' => 'Regular Post',
            'slug' => 'regular-post',
            'content' => 'This is a regular post.',
        ]);

        $response = $this->getJson("/entry/batch/post?slugs=" . urlencode("{$post1->slug},{$post2->slug}"));

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.title', 'Post with, comma')
            ->assertJsonPath('data.1.title', 'Regular Post');
    }
}
