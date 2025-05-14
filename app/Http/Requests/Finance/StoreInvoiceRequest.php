<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class StoreInvoiceRequest extends FormRequest
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
            'card_id' => 'required|string|exists:cards,id',
            'reference_date' => 'required|date',
            'total_amount' => 'required|integer',
            'status' => 'required|string|in:Pendente,Pago,Atrasado,Processando',
            'due_date' => 'sometimes|nullable|date',
            'closing_date' => 'sometimes|nullable|date',
            'notes' => 'sometimes|nullable|string',
            'transactions' => 'sometimes|array',
            'transactions.*.merchant_name' => 'required|string',
            'transactions.*.transaction_date' => 'required|date',
            'transactions.*.amount' => 'required|integer',
            'transactions.*.category_id' => 'sometimes|nullable|string|exists:categories,id',
            'transactions.*.description' => 'sometimes|nullable|string',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'card_id.required' => 'É necessário informar o cartão relacionado à fatura.',
            'card_id.exists' => 'O cartão informado não existe.',
            'reference_date.required' => 'A data de referência é obrigatória.',
            'reference_date.date' => 'A data de referência deve ser uma data válida.',
            'total_amount.required' => 'O valor total da fatura é obrigatório.',
            'total_amount.integer' => 'O valor total da fatura deve ser um número inteiro (em centavos).',
            'status.required' => 'O status da fatura é obrigatório.',
            'status.in' => 'O status da fatura deve ser Pendente, Pago, Atrasado ou Processando.',
        ];
    }
}