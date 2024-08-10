<?php

namespace Database\Factories;

use App\Models\Entry;
use App\Models\Image;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Storage;
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
            'content' => $this->generateMarkdownLikeContent(),
            'excerpt' => $this->faker->paragraph,
            'filename' => $this->faker->word.'.md',
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

    protected function generateMarkdownLikeContent(): string
    {
        $content = '# '.$this->faker->sentence."\n\n";
        $content .= $this->faker->paragraph."\n\n";
        $content .= '## '.$this->faker->sentence."\n\n";
        $content .= '- '.$this->faker->sentence."\n";
        $content .= '- '.$this->faker->sentence."\n";
        $content .= '- '.$this->faker->sentence."\n\n";
        $content .= $this->faker->paragraph."\n\n";
        $content .= '### '.$this->faker->sentence."\n\n";
        $content .= $this->faker->paragraph;

        return $content;
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

    public function withTags(?array $tags = null): self
    {
        return $this->afterCreating(function (Entry $entry) use ($tags) {
            $tagsToAttach = $tags ?? $this->faker->words(3);
            $entry->attachTags($tagsToAttach);
        });
    }

    public function withRealMarkdown(int $numberOfImages = 2): self
    {
        return $this->afterCreating(function (Entry $entry) use ($numberOfImages) {
            $content = $this->generateMarkdownContent($numberOfImages);
            $frontMatter = $this->generateFrontMatter($entry, $numberOfImages);

            $markdownContent = $frontMatter."\n\n".$content;

            $filename = $entry->slug.'.md';
            $path = Storage::disk('local')->path($filename);

            file_put_contents($path, $markdownContent);

            $entry->update([
                'filename' => $filename,
                'content' => $content,
            ]);

            // Clean up the file after the test
            register_shutdown_function(function () use ($path) {
                if (file_exists($path)) {
                    unlink($path);
                }
            });
        });
    }

    protected function generateMarkdownContent(int $numberOfImages): string
    {
        $content = '# '.$this->faker->sentence."\n\n";
        $content .= $this->faker->paragraphs(3, true)."\n\n";

        for ($i = 1; $i <= $numberOfImages; $i++) {
            $content .= "![Image $i](image$i.jpg)\n\n";
            $content .= $this->faker->paragraph."\n\n";
        }

        $content .= '## '.$this->faker->sentence."\n\n";
        $content .= $this->faker->paragraphs(2, true);

        return $content;
    }

    protected function generateFrontMatter(Entry $entry, int $numberOfImages): string
    {
        $frontMatter = "---\n";
        $frontMatter .= 'title: '.$entry->title."\n";
        $frontMatter .= 'slug: '.$entry->slug."\n";
        $frontMatter .= 'type: '.$entry->type."\n";
        $frontMatter .= 'published_at: '.($entry->published_at ? $entry->published_at->format('Y-m-d H:i:s') : 'null')."\n";

        for ($i = 1; $i <= $numberOfImages; $i++) {
            $frontMatter .= "image$i: image$i.jpg\n";
        }

        foreach ($entry->meta as $key => $value) {
            if (is_array($value)) {
                $frontMatter .= "$key:\n";
                foreach ($value as $subKey => $subValue) {
                    $frontMatter .= "  $subKey: ".json_encode($subValue)."\n";
                }
            } else {
                $frontMatter .= "$key: ".json_encode($value)."\n";
            }
        }

        $frontMatter .= '---';

        return $frontMatter;
    }

    public function withImages(int $count = 1, bool $realImages = false): self
    {
        return $this->afterCreating(function (Entry $entry) use ($count, $realImages) {
            $imageFactory = Image::factory()->count($count);

            if ($realImages) {
                $imageFactory->withRealImage();
            }

            $images = $imageFactory->create(['entry_id' => $entry->id]);

            // Update the entry's meta to include the image filenames
            $meta = $entry->meta;
            $meta['images'] = $images->pluck('filename')->toArray();
            $entry->update(['meta' => $meta]);
        });
    }
}
