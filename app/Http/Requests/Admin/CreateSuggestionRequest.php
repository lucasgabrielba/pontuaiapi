<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CreateSuggestionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Only admins can create suggestions
        return auth()->user() && (
            auth()->user()->hasRole('admin') || 
            auth()->user()->hasRole('super_admin')
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'type' => 'sometimes|string|in:optimization,problem,enhancement,general',
            'priority' => 'sometimes|string|in:low,medium,high,critical',
            'additional_data' => 'sometimes|array',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'title.required' => 'O título da sugestão é obrigatório.',
            'title.max' => 'O título deve ter no máximo 255 caracteres.',
            'description.required' => 'A descrição da sugestão é obrigatória.',
            'type.in' => 'O tipo deve ser: optimization, problem, enhancement ou general.',
            'priority.in' => 'A prioridade deve ser: low, medium, high ou critical.',
        ];
    }
}