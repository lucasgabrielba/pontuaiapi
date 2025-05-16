<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBankRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $bankId = $this->route('bank');
        
        return [
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|nullable|string|max:50|unique:banks,code,' . $bankId,
            'logo_url' => 'sometimes|nullable|url|max:255',
            'primary_color' => 'sometimes|nullable|string|max:50',
            'secondary_color' => 'sometimes|nullable|string|max:50',
            'is_active' => 'sometimes|boolean',
            'description' => 'sometimes|nullable|string',
        ];
    }
}