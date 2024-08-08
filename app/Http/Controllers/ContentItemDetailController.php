<?php

namespace App\Http\Controllers;

use App\Models\ContentItem;
use App\Filters\ContentItemArrayConverter;
use App\Http\Requests\ListRequest;

class ContentItemDetailController extends Controller
{
    public function __construct(
        protected ContentItemArrayConverter $arrayConverter
    ) {}

    public function show(ListRequest $request, $type, $slug)
    {
        $contentItem = ContentItem::where('type', $type)
            ->where('slug', $slug)
            ->firstOrFail();

        $fields = $request->getFields();

        return response()->json(
            $this->arrayConverter->toDetailArray($contentItem, $fields)
        );
    }
}
