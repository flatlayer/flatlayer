<?php

namespace App\Http\Controllers;

use App\Models\Media;
use Illuminate\Http\Request;
use Intervention\Image\Drivers\Imagick\Driver;
use Intervention\Image\ImageManager;

class ImageManipulationController extends Controller
{
    protected ImageManager $manager;

    public function __construct()
    {
        $this->manager = new ImageManager(new Driver());
    }

    public function transform(Request $request, Media $media)
    {
        $transformations = json_decode(base64_decode($request->transformations), true);

        $image = $this->manager->read($media->getPath());

        foreach ($transformations as $method => $params) {
            if (method_exists($image, $method)) {
                $image = call_user_func_array([$image, $method], $params);
            }
        }

        return $image->toResponse($request);
    }
}
