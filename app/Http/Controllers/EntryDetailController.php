<?php

namespace App\Http\Controllers;

use App\Models\Entry;
use App\Query\EntrySerializer;
use App\Http\Requests\ListRequest;

class EntryDetailController extends Controller
{
    public function __construct(
        protected EntrySerializer $arrayConverter
    ) {}

    public function show(ListRequest $request, $type, $slug)
    {
        $contentItem = Entry::where('type', $type)
            ->where('slug', $slug)
            ->firstOrFail();

        $fields = $request->getFields();

        return response()->json(
            $this->arrayConverter->toDetailArray($contentItem, $fields)
        );
    }
}
