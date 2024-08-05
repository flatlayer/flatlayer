<?php

namespace Database\Factories;

use App\Models\Media;
use Illuminate\Database\Eloquent\Factories\Factory;

class MediaFactory extends Factory
{
    protected $model = Media::class;

    public function definition()
    {
        return [
            'model_type' => $this->faker->word,
            'model_id' => $this->faker->randomNumber(),
            'collection' => $this->faker->word,
            'path' => $this->faker->filePath(),
            'mime_type' => $this->faker->mimeType(),
            'size' => $this->faker->numberBetween(1000, 10000000),
            'dimensions' => [
                'width' => $this->faker->numberBetween(100, 2000),
                'height' => $this->faker->numberBetween(100, 2000),
            ],
            'custom_properties' => [
                'alt' => $this->faker->sentence,
            ],
        ];
    }
}
