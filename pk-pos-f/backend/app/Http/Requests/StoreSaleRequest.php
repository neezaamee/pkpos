<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSaleRequest extends FormRequest
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
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'id' => ['required', 'uuid'],
            'location_id' => ['required', 'uuid', 'exists:locations,id'],
            'sale_number' => ['required', 'string', 'max:50'],
            'payment_method' => ['required', 'string', 'in:cash,card,raast,wallet'],
            'currency' => ['nullable', 'string', 'size:3'],
            'subtotal_minor' => ['required', 'integer', 'min:0'],
            'tax_minor' => ['required', 'integer', 'min:0'],
            'total_minor' => ['required', 'integer', 'min:0'],
            'status' => ['required', 'string', 'in:completed,refunded,voided'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_name' => ['required', 'string', 'max:255'],
            'items.*.hs_code' => ['required', 'string', 'max:50'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price_minor' => ['required', 'integer', 'min:0'],
            'items.*.tax_rate_basis_points' => ['required', 'integer', 'min:0'],
            'items.*.tax_amount_minor' => ['required', 'integer', 'min:0'],
            'items.*.line_total_minor' => ['required', 'integer', 'min:0'],
        ];
    }
}
