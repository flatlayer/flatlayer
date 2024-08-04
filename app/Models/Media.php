<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;

class Media extends Model
{
    protected $fillable = [
        'model_type',
        'model_id',
        'collection_name',
        'path',
        'mime_type',
        'size',
        'custom_properties',
    ];

    protected $casts = [
        'custom_properties' => 'array',
        'size' => 'integer',
    ];

    public function model()
    {
        return $this->morphTo();
    }

    public function getUrl(array $transformations = []): string
    {
        return URL::signedRoute('media.transform', [
            'media' => $this->id,
            'transformations' => base64_encode(json_encode($transformations))
        ]);
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getFileName(): string
    {
        return basename($this->path);
    }

    public static function addMediaToModel($model, string $path, string $collectionName = 'default')
    {
        if (!file_exists($path)) {
            throw new \InvalidArgumentException("File does not exist at path: $path");
        }

        $media = new static();
        $media->model_type = get_class($model);
        $media->model_id = $model->id;
        $media->collection_name = $collectionName;
        $media->path = $path;
        $media->mime_type = mime_content_type($path);
        $media->size = filesize($path);

        $media->save();

        return $media;
    }
}
