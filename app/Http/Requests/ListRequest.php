<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class ListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'query' => 'sometimes|string|max:255',
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'filters' => 'sometimes|array',
            'filters.*' => 'string|array',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('filters')) {
            $this->merge([
                'filters' => $this->parseFilters($this->filters)
            ]);
        }
    }

    protected function parseFilters(array $filters): array
    {
        $parsedFilters = [];

        foreach ($filters as $key => $value) {
            if (Str::startsWith($key, 'tag:')) {
                $tagType = Str::after($key, 'tag:');
                $parsedFilters['tags'][$tagType] = $value;
            } elseif ($key === 'tag') {
                $parsedFilters['tags']['default'] = is_array($value) ? $value : [$value];
            } else {
                $parsedFilters[$key] = $value;
            }
        }

        return $parsedFilters;
    }

    public function getModelClass(): ?string
    {
        $modelSlug = $this->route('modelSlug');
        $modelName = Str::studly($modelSlug);
        $modelClass = "App\\Models\\{$modelName}";

        return class_exists($modelClass) ? $modelClass : null;
    }

    public function filters(): array
    {
        return $this->validated('filters', []);
    }
}
