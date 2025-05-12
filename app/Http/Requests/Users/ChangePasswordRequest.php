<?php

namespace App\Http\Requests\Users;

use Illuminate\Foundation\Http\FormRequest;

class ChangePasswordRequest extends FormRequest
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
            'currentPassword' => 'required|string',
            'newPassword' => 'required|string',
        ];
    }

    /**
     * Customize the error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'currentPassword.required' => 'The current password is required.',
            'currentPassword.string' => 'The current password must be a string.',
            'currentPassword.min' => 'The current password must be at least 8 characters.',
            'newPassword.required' => 'The new password is required.',
            'newPassword.string' => 'The new password must be a string.',
            'newPassword.min' => 'The new password must be at least 8 characters.',
        ];
    }
}
