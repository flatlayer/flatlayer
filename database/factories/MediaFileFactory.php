<?php

namespace Database\Factories;

use App\Models\MediaFile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class MediaFileFactory extends Factory
{
    protected $model = MediaFile::class;

    public function definition()
    {
        return [
            'model_type' => $this->faker->word,
            'model_id' => $this->faker->randomNumber(),
            'collection' => $this->faker->word,
            'filename' => $this->faker->word . '.jpg',
            'path' => $this->faker->filePath(),
            'mime_type' => $this->faker->mimeType(),
            'size' => $this->faker->numberBetween(1000, 10000000),
            'dimensions' => json_encode([
                'width' => $this->faker->numberBetween(100, 2000),
                'height' => $this->faker->numberBetween(100, 2000),
            ]),
            'custom_properties' => json_encode([
                'alt' => $this->faker->sentence,
            ]),
            'thumbhash' => Str::random(32),
        ];
    }
}
