<?php

namespace App\Http\Requests;

use App\Support\Path;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class BatchShowRequest extends FormRequest
{
    protected array $includedFeatures = [];

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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'slugs' => [
                'required',
                'string',
                'max:1024',
                function ($attribute, $value, $fail) {
                    $slugs = explode(',', $value);
                    foreach ($slugs as $slug) {
                        $sanitized = Path::toSlug($slug);
                        if ($sanitized !== $slug) {
                            $fail('Invalid path format.');

                            return;
                        }
                    }
                },
            ],
            'fields' => [
                'sometimes',
                Rule::when(is_string($this->input('fields')), 'json'),
            ],
            'includes' => 'sometimes|string',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->decodeJsonInput('fields');

        if ($this->has('includes')) {
            $this->includedFeatures = array_filter(explode(',', $this->input('includes')));
        }
    }

    /**
     * Decode JSON input if it's a string.
     */
    private function decodeJsonInput(string $field): void
    {
        if ($this->has($field) && is_string($this->input($field))) {
            $decoded = json_decode($this->input($field), true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $this->merge([$field => $decoded]);
            }
        }
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fields.json' => 'The fields must be a valid JSON string.',
            'includes.string' => 'The includes parameter must be a comma-separated string.',
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'error' => $validator->errors()->first(),
        ], 400));
    }

    /**
     * Check if a specific feature should be included in the response.
     */
    public function includes(string $feature): bool
    {
        return in_array($feature, $this->includedFeatures);
    }

    /**
     * Get the fields array from the request.
     *
     * @return array<string, mixed>
     */
    public function getFields(): array
    {
        return $this->input('fields', []);
    }

    /**
     * Get the slugs array from the request.
     *
     * @return array<string>
     */
    public function getSlugs(): array
    {
        return explode(',', $this->input('slugs', ''));
    }
}
