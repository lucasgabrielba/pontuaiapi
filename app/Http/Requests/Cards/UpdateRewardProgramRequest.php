<?php

namespace App\Http\Requests\Cards;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRewardProgramRequest extends FormRequest
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
        $programId = $this->route('reward_program');
        
        return [
            'name' => 'sometimes|string|max:255|unique:reward_programs,name,' . $programId,
            'code' => 'sometimes|nullable|string|max:50|unique:reward_programs,code,' . $programId,
            'description' => 'sometimes|nullable|string',
            'website' => 'sometimes|nullable|url',
            'logo' => 'sometimes|nullable|image|max:2048', // Max 2MB
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'Este nome de programa já está cadastrado.',
            'code.unique' => 'Este código de programa já está cadastrado.',
            'website.url' => 'O website deve ser uma URL válida.',
            'logo.image' => 'O arquivo de logo deve ser uma imagem.',
            'logo.max' => 'O tamanho máximo da logo é 2MB.',
        ];
    }
}