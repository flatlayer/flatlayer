<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SingleModelController extends Controller
{
    public function show(Request $request, $modelSlug, $slug)
    {
        $modelName = Str::studly($modelSlug);
        $modelClass = "App\\Models\\{$modelName}";

        if (!class_exists($modelClass)) {
            return response()->json(['error' => 'Invalid model'], 400);
        }

        $model = $modelClass::where('slug', $slug)->firstOrFail();

        return response()->json($model->toArray());
    }
}
