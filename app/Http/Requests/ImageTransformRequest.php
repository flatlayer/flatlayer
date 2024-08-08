<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImageTransformRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'w' => 'sometimes|integer|min:1',
            'h' => 'sometimes|integer|min:1',
            'q' => 'sometimes|integer|between:1,100',
            'fm' => 'sometimes|in:jpg,png,webp',
        ];
    }

    public function validationData()
    {
        return array_merge($this->query(), $this->route()->parameters());
    }
}
