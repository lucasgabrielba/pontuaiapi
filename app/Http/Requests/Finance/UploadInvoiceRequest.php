<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class UploadInvoiceRequest extends FormRequest
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
            'invoice_file' => 'required|file|mimes:pdf,jpg,jpeg,png,csv|max:10240',
            'card_id' => 'required|string|exists:cards,id',
            'reference_date' => 'sometimes|date',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'invoice_file.required' => 'O arquivo da fatura é obrigatório.',
            'invoice_file.file' => 'O arquivo enviado deve ser um arquivo válido.',
            'invoice_file.mimes' => 'O arquivo deve ser um PDF, JPG, PNG ou CSV.',
            'invoice_file.max' => 'O tamanho máximo do arquivo é 10MB.',
            'card_id.required' => 'O ID do cartão é obrigatório.',
            'card_id.exists' => 'O cartão informado não existe.',
            'card_name.string' => 'O nome do cartão deve ser uma string.',
            'reference_date.date' => 'A data de referência deve ser uma data válida.',
        ];
    }
}