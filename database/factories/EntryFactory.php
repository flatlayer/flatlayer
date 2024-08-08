<?php

namespace Database\Factories;

use App\Models\Entry;
use App\Services\JinaSearchService;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class EntryFactory extends Factory
{
    protected $model = Entry::class;

    public function definition(): array
    {
        $title = $this->faker->sentence;
        return [
            'type' => $this->faker->randomElement(['post', 'document']),
            'title' => $title,
            'slug' => Str::slug($title),
            'content' => $this->faker->paragraphs(3, true),
            'excerpt' => $this->faker->paragraph,
            'filename' => $this->faker->filePath() . '/' . $this->faker->word . '.md',
            'meta' => [
                'author' => $this->faker->name,
                'reading_time' => $this->faker->numberBetween(1, 20),
                'category' => $this->faker->word,
                'featured_image' => $this->faker->imageUrl(),
                'seo' => [
                    'meta_description' => $this->faker->sentence,
                    'meta_keywords' => $this->faker->words(5, true),
                ],
                'version' => $this->faker->semver,
                'last_updated' => $this->faker->dateTimeThisYear()->format('Y-m-d'),
            ],
            'published_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
        ];
    }

    public function unpublished(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'published_at' => null,
            ];
        });
    }

    public function post(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'type' => 'post',
                'meta' => array_merge($attributes['meta'] ?? [], [
                    'comments_count' => $this->faker->numberBetween(0, 100),
                    'likes_count' => $this->faker->numberBetween(0, 500),
                ]),
            ];
        });
    }

    public function document(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'type' => 'document',
                'meta' => array_merge($attributes['meta'] ?? [], [
                    'document_type' => $this->faker->randomElement(['guide', 'api', 'tutorial', 'reference']),
                    'target_audience' => $this->faker->randomElement(['beginner', 'intermediate', 'advanced']),
                ]),
            ];
        });
    }
}
