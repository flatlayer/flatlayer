<?php

namespace App\Http\Controllers;

use App\Services\ModelResolverService;
use Illuminate\Http\Request;

class ModelDetailController extends Controller
{
    protected $modelResolver;

    public function __construct(ModelResolverService $modelResolver)
    {
        $this->modelResolver = $modelResolver;
    }

    public function show(Request $request, $modelSlug, $slug)
    {
        $modelClass = $this->modelResolver->resolve($modelSlug);

        if (!$modelClass) {
            return response()->json(['error' => 'Invalid model'], 400);
        }

        $model = $modelClass::where('slug', $slug)->firstOrFail();

        return response()->json(
            method_exists($model, 'toDetailArray') ?
                $model->toDetailArray() :
                $model->toArray()
        );
    }
}
