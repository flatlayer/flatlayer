<?php

namespace App\Http\Requests;

use App\Services\ModelResolverService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'search' => 'sometimes|string|max:255',
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'filter' => [
                'sometimes',
                Rule::when(is_string($this->input('filter')), 'json'),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('filter') && is_string($this->input('filter'))) {
            $decodedFilter = json_decode($this->input('filter'), true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $this->merge(['filter' => $decodedFilter]);
            }
            // If JSON is invalid, we leave it as a string so that the 'json' rule can catch it
        }
    }

    public function messages()
    {
        return [
            'filter.json' => 'The filter must be a valid JSON string.',
        ];
    }

    public function getModelClass(): ?string
    {
        $modelSlug = $this->route('modelSlug');
        return $this->modelResolver->resolve($modelSlug);
    }

    public function getFilter(): array
    {
        $filter = $this->input('filter', []);
        if ($this->has('search')) {
            $filter['$search'] = $this->input('search');
        }
        return $filter;
    }
}
