<?php

namespace App\Http\Requests\Cards;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCardRequest extends FormRequest
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
            'name' => 'sometimes|string|max:255',
            'bank' => 'sometimes|string|max:255',
            'last_digits' => 'sometimes|string|size:4|regex:/^\d{4}$/',
            'conversion_rate' => 'sometimes|numeric|min:0',
            'annual_fee' => 'sometimes|nullable|numeric|min:0',
            'active' => 'sometimes|boolean',
            'reward_programs' => 'sometimes|array',
            'reward_programs.*.reward_program_id' => 'required|string|exists:reward_programs,id',
            'reward_programs.*.conversion_rate' => 'sometimes|numeric|min:0',
            'reward_programs.*.is_primary' => 'sometimes|boolean',
            'reward_programs.*.terms' => 'sometimes|nullable|string',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'name.string' => 'O nome do cartão deve ser um texto.',
            'bank.string' => 'O banco emissor do cartão deve ser um texto.',
            'last_digits.size' => 'Os últimos dígitos devem ter exatamente 4 números.',
            'last_digits.regex' => 'Os últimos dígitos devem conter apenas números.',
            'conversion_rate.numeric' => 'A taxa de conversão deve ser um número.',
            'conversion_rate.min' => 'A taxa de conversão não pode ser negativa.',
            'annual_fee.numeric' => 'A taxa anual deve ser um número.',
            'annual_fee.min' => 'A taxa anual não pode ser negativa.',
        ];
    }
}