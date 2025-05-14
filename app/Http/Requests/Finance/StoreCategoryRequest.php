<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class StoreCategoryRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:categories,code',
            'description' => 'sometimes|nullable|string',
            'icon' => 'sometimes|nullable|string|max:100',
            'color' => 'sometimes|nullable|string|max:50',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'O nome da categoria é obrigatório.',
            'code.required' => 'O código da categoria é obrigatório.',
            'code.unique' => 'Este código de categoria já está cadastrado.',
        ];
    }
}