<?php

namespace Database\Factories;

use App\Models\Image;
use App\Models\Entry;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class ImageFactory extends Factory
{
    protected $model = Image::class;

    public function definition()
    {
        return [
            'entry_id' => Entry::factory(),
            'collection' => $this->faker->word,
            'filename' => $this->faker->word . '.jpg',
            'path' => $this->faker->filePath(),
            'mime_type' => 'image/jpeg',
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

    public function withRealImage($width = 640, $height = 480)
    {
        return $this->state(function (array $attributes) use ($width, $height) {
            $manager = new ImageManager(new Driver());
            $image = $manager->create($width, $height, function ($draw) use ($width, $height) {
                $draw->background('#'.substr(md5(mt_rand()), 0, 6));
                $draw->text('Test Image', $width / 2, $height / 2, function ($font) {
                    $font->color('#ffffff');
                    $font->align('center');
                    $font->valign('middle');
                    $font->size(24);
                });
            });

            $tempPath = tempnam(sys_get_temp_dir(), 'test_image_') . '.jpg';
            $image->toJpeg()->save($tempPath);

            return [
                'filename' => basename($tempPath),
                'path' => $tempPath,
                'mime_type' => 'image/jpeg',
                'size' => filesize($tempPath),
                'dimensions' => json_encode(['width' => $width, 'height' => $height]),
            ];
        })->afterCreating(function (Image $image) {
            // Register a shutdown function to delete the temporary file
            register_shutdown_function(function () use ($image) {
                if (file_exists($image->path)) {
                    unlink($image->path);
                }
            });
        });
    }
}
