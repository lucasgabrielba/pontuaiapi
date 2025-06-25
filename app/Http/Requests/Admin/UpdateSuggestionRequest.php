<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSuggestionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Only admins can update suggestions
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
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'type' => 'sometimes|string|in:optimization,problem,enhancement,general',
            'priority' => 'sometimes|string|in:low,medium,high,critical',
            'status' => 'sometimes|string|in:pending,in_progress,completed,rejected',
            'additional_data' => 'sometimes|array',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'title.max' => 'O título deve ter no máximo 255 caracteres.',
            'type.in' => 'O tipo deve ser: optimization, problem, enhancement ou general.',
            'priority.in' => 'A prioridade deve ser: low, medium, high ou critical.',
            'status.in' => 'O status deve ser: pending, in_progress, completed ou rejected.',
        ];
    }
}