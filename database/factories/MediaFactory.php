<?php

namespace Database\Factories;

use App\Models\Media;
use Illuminate\Database\Eloquent\Factories\Factory;

class MediaFactory extends Factory
{
    protected $model = Media::class;

    public function definition()
    {
        $path = $this->faker->filePath();

        return [
            'model_type' => $this->faker->word,
            'model_id' => $this->faker->randomNumber(),
            'collection' => 'default',
            'filename' => basename($path),
            'path' => $path,
            'mime_type' => $this->faker->mimeType,
            'size' => $this->faker->numberBetween(1000, 1000000),
            'dimensions' => [
                'width' => $this->faker->numberBetween(100, 1000),
                'height' => $this->faker->numberBetween(100, 1000),
            ],
            'thumbhash' => $this->faker->sha256,
            'custom_properties' => ['alt' => $this->faker->sentence],
        ];
    }
}
