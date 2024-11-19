<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class HierarchyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'root' => 'sometimes|string|max:1024',
            'depth' => 'sometimes|integer|min:1|max:10',
            'fields' => [
                'sometimes',
                'array',
                'min:1',
            ],
            'fields.*' => [
                'string',
                'regex:/^(id|title|slug|is_index|meta\..+)$/',
            ],
            'sort' => 'sometimes|array',
            'sort.*' => 'in:asc,desc',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->mergeIfJson('fields');
        $this->mergeIfJson('sort');
    }

    private function mergeIfJson(string $field): void
    {
        if ($this->has($field) && is_string($this->input($field))) {
            $decoded = json_decode($this->input($field), true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $this->merge([$field => $decoded]);
            }
        }
    }

    public function messages(): array
    {
        return [
            'fields.*.regex' => 'Invalid field specified. Allowed fields: id, title, slug, is_index, meta.*',
            'sort.*.in' => 'Sort direction must be either "asc" or "desc"',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'error' => $validator->errors()->first(),
        ], 400));
    }

    public function getOptions(): array
    {
        return array_filter([
            'depth' => $this->input('depth'),
            'fields' => $this->input('fields'),
            'sort' => $this->input('sort'),
        ]);
    }

    public function getRoot(): ?string
    {
        return $this->input('root');
    }
}
