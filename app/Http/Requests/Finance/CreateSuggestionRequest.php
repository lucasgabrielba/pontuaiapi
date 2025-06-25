<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class CreateSuggestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:80',
            'description' => 'required|string|max:500',
            'type' => 'required|string|in:card_recommendation,merchant_recommendation,category_optimization,points_strategy,general_tip',
            'priority' => 'sometimes|string|in:low,medium,high',
            'recommendation' => 'required|string|max:300',
            'impact_description' => 'nullable|string|max:120',
            'potential_points_increase' => 'nullable|string|max:32',
            'is_personalized' => 'sometimes|boolean',
            'applies_to_future' => 'sometimes|boolean',
            'additional_data' => 'sometimes|array',
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'O título é obrigatório.',
            'title.max' => 'O título deve ter no máximo 80 caracteres.',
            'description.required' => 'A descrição é obrigatória.',
            'description.max' => 'A descrição deve ter no máximo 500 caracteres.',
            'type.required' => 'O tipo de sugestão é obrigatório.',
            'type.in' => 'Tipo de sugestão inválido.',
            'recommendation.required' => 'A recomendação é obrigatória.',
            'recommendation.max' => 'A recomendação deve ter no máximo 300 caracteres.',
            'priority.in' => 'Prioridade deve ser: low, medium ou high.',
        ];
    }
}