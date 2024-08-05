<?php

namespace App\Http\Requests;

use App\Services\ModelResolverService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class ListRequest extends FormRequest
{
    protected $modelResolver;

    public function __construct(ModelResolverService $modelResolver)
    {
        $this->modelResolver = $modelResolver;
    }

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
            'filters.*' => 'array',
            'filters.*.*' => 'string',
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
        return $this->modelResolver->resolve($modelSlug);
    }

    public function filters(): array
    {
        return $this->validated('filters', []);
    }
}
