<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PenyesuaianStokRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $aturanPerubahan = [
            'required',
            'integer',
            'not_in:0',
        ];

        if ($this->input('alasan') === 'penjualan') {
            $aturanPerubahan[] = 'max:-1';
        }

        if ($this->input('alasan') === 'restok') {
            $aturanPerubahan[] = 'min:1';
        }

        return [
            'operation_id' => ['required', 'uuid'],
            'perubahan' => $aturanPerubahan,
            'alasan' => ['required', 'in:penjualan,restok,koreksi'],
        ];
    }
}
