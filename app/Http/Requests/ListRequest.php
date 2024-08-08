<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListRequest extends FormRequest
{
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
            'fields' => [
                'sometimes',
                Rule::when(is_string($this->input('fields')), 'json'),
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
        }

        if ($this->has('fields') && is_string($this->input('fields'))) {
            $decodedFields = json_decode($this->input('fields'), true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $this->merge(['fields' => $decodedFields]);
            }
        }
    }

    public function messages()
    {
        return [
            'fields.json' => 'The fields must be a valid JSON string.',
        ];
    }

    public function getFilter(): array
    {
        $filter = $this->input('filter', []);
        if ($this->has('search')) {
            $filter['$search'] = $this->input('search');
        }
        return $filter;
    }

    public function getFields(): array
    {
        return $this->input('fields', []);
    }
}
