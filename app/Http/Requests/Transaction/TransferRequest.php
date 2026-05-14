<?php

namespace App\Http\Requests\Transaction;

use Illuminate\Foundation\Http\FormRequest;

class TransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from_account_id' => ['required', 'integer', 'exists:accounts,id'],
            'to_account_id'   => ['required', 'integer', 'exists:accounts,id', 'different:from_account_id'],
            'amount'          => ['required', 'numeric', 'min:0.01'],
        ];
    }
}
