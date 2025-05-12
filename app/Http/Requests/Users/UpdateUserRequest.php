<?php

namespace App\Http\Requests\Users;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
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
        $userId = $this->route('user');

        return [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,'.$userId,
            'password' => 'sometimes|nullable|string|min:8|confirmed',
            'password_confirmation' => 'sometimes|string|min:8|same:password',
            'status' => 'sometimes|string|in:Ativo,Inativo',
        ];
    }

    /**
     * Customize the error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'name.string' => 'O nome deve ser uma string.',
            'name.max' => 'O nome deve ter no máximo 255 caracteres.',
            'email.email' => 'O e-mail deve ser um endereço de e-mail válido.',
            'email.unique' => 'Este e-mail já está sendo usado.',
            'password.min' => 'A senha deve ter pelo menos 8 caracteres.',
            'password_confirmation.min' => 'A confirmação de senha deve ter pelo menos 8 caracteres.',
            'password.same' => 'A senha e a confirmação de senha devem ser iguais.',
            'status.in' => 'O status deve ser Ativo ou Inativo.',
        ];
    }
}
