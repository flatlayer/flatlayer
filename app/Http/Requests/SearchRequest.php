<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class SearchRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\Rule|array|string>
     */
    public function rules(): array
    {
        return [
            'query' => 'required|string|max:255',
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'filters' => 'sometimes|array',
            'filters.*' => 'string|array',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('filters')) {
            $this->merge([
                'filters' => $this->parseFilters($this->filters)
            ]);
        }
    }

    /**
     * Parse the filters from the request.
     *
     * @param array $filters
     * @return array
     */
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

    /**
     * Get the model class for the search.
     */
    public function getModelClass(): ?string
    {
        $modelSlug = $this->route('modelSlug');
        $modelName = Str::studly($modelSlug);
        $modelClass = "App\\Models\\{$modelName}";

        return class_exists($modelClass) ? $modelClass : null;
    }

    /**
     * Get the validated filters.
     */
    public function filters(): array
    {
        return $this->validated('filters', []);
    }
}
