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
            'customer_id' => ['nullable', 'uuid', 'exists:customers,id'],
            'location_id' => ['required', 'uuid', 'exists:locations,id'],
            'sale_number' => ['required', 'string', 'max:50'],
            'payment_method' => ['required', 'string', 'in:cash,card,raast,wallet,credit,cheque,split'],
            'currency' => ['nullable', 'string', 'size:3'],
            'subtotal_minor' => ['required', 'integer', 'min:0'],
            'tax_minor' => ['required', 'integer', 'min:0'],
            'total_minor' => ['required', 'integer', 'min:0'],
            'status' => ['required', 'string', 'in:completed,refunded,voided'],
            'prescription' => ['nullable', 'array'],
            'prescription.patient_name' => ['required_with:prescription', 'string', 'max:100'],
            'prescription.patient_phone' => ['nullable', 'string', 'max:50'],
            'prescription.patient_cnic' => ['nullable', 'string', 'max:20'],
            'prescription.doctor_name' => ['required_with:prescription', 'string', 'max:100'],
            'prescription.doctor_pmdc_number' => ['required_with:prescription', 'string', 'max:50'],
            'prescription.hospital_clinic_name' => ['nullable', 'string', 'max:150'],
            'prescription.prescription_date' => ['nullable', 'date'],
            'prescription.diagnosis_notes' => ['nullable', 'string', 'max:500'],
            'payments' => ['nullable', 'array', 'min:1'],
            'payments.*.payment_method' => ['required', 'string', 'in:cash,card,raast,wallet,credit,cheque'],
            'payments.*.amount_minor' => ['required', 'integer', 'gt:0'],
            'payments.*.tendered_minor' => ['nullable', 'integer', 'min:0'],
            'payments.*.change_minor' => ['nullable', 'integer', 'min:0'],
            'payments.*.reference' => ['nullable', 'string', 'max:100'],
            'payments.*.reference_number' => ['nullable', 'string', 'max:100'],
            'payments.*.notes' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', 'uuid'],
            'items.*.product_name' => ['required', 'string', 'max:255'],
            'items.*.hs_code' => ['required', 'string', 'max:50'],
            'items.*.unit_code' => ['nullable', 'string', 'max:20'],
            'items.*.unit_conversion_factor' => ['nullable', 'numeric', 'gt:0'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price_minor' => ['required', 'integer', 'min:0'],
            'items.*.tax_rate_basis_points' => ['required', 'integer', 'min:0'],
            'items.*.tax_amount_minor' => ['required', 'integer', 'min:0'],
            'items.*.line_total_minor' => ['required', 'integer', 'min:0'],
            'items.*.discount_type' => ['nullable', 'string', 'in:none,percentage,fixed'],
            'items.*.discount_amount_minor' => ['nullable', 'integer', 'min:0'],
            'items.*.batch_id' => ['nullable', 'uuid', 'exists:product_batches,id'],
            'items.*.batch_number' => ['nullable', 'string', 'max:50'],
            'items.*.expiry_date' => ['nullable', 'date'],
            'items.*.selected_modifiers' => ['nullable', 'array'],
            'items.*.cooking_notes' => ['nullable', 'string', 'max:255'],
            'items.*.kitchen_station_id' => ['nullable', 'uuid', 'exists:kitchen_stations,id'],
            'order_type' => ['nullable', 'string', 'in:dine_in,takeaway,delivery'],
            'restaurant_table_id' => ['nullable', 'uuid', 'exists:restaurant_tables,id'],
            'table_number' => ['nullable', 'string', 'max:50'],
            'server_user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'server_name' => ['nullable', 'string', 'max:100'],
            'covers' => ['nullable', 'integer', 'min:1'],
            'service_charge_basis_points' => ['nullable', 'integer', 'min:0'],
            'fire_kot' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $payments = $this->input('payments');
            if (is_array($payments) && count($payments) > 0) {
                $totalPaid = array_sum(array_column($payments, 'amount_minor'));
                $totalMinor = (int) $this->input('total_minor');
                if ($totalPaid !== $totalMinor) {
                    $paidRs = number_format($totalPaid / 100, 2);
                    $dueRs = number_format($totalMinor / 100, 2);
                    $validator->errors()->add(
                        'payments',
                        "Total allocated payments (Rs. {$paidRs}) must equal invoice total (Rs. {$dueRs})."
                    );
                }
            }
        });
    }
}
