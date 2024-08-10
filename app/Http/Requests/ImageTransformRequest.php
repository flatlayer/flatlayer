<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Handles validation for image transformation requests.
 */
class ImageTransformRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'w' => 'sometimes|integer|min:1|max:' . config('flatlayer.images.max_width', 5000),
            'h' => 'sometimes|integer|min:1|max:' . config('flatlayer.images.max_height', 5000),
            'q' => 'sometimes|integer|between:1,100',
            'fm' => 'sometimes|in:jpg,jpeg,png,webp,gif',
        ];
    }

    public function messages(): array
    {
        return [
            'w.integer' => 'Invalid width parameter',
            'w.min' => 'Width must be at least 1 pixel',
            'w.max' => 'Requested width exceeds maximum allowed',
            'h.integer' => 'Invalid height parameter',
            'h.min' => 'Height must be at least 1 pixel',
            'h.max' => 'Requested height exceeds maximum allowed',
            'q.integer' => 'Invalid quality parameter',
            'q.between' => 'Quality must be between 1 and 100',
            'fm.in' => 'Invalid format parameter',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'error' => $validator->errors()->first()
        ], 400));
    }

    public function validationData(): array
    {
        return array_merge($this->query(), $this->route()->parameters());
    }
}
