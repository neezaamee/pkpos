<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'tenant_name' => $this->input('tenant_name') ?? $this->input('tenant.name') ?? $this->input('name'),
            'user_name' => $this->input('user_name') ?? $this->input('user.name') ?? $this->input('name'),
            'ntn' => $this->input('ntn') ?? $this->input('tenant.ntn'),
            'strn' => $this->input('strn') ?? $this->input('tenant.strn'),
            'province' => $this->input('province') ?? $this->input('tenant.province'),
            'fbr_pos_id' => $this->input('fbr_pos_id') ?? $this->input('tenant.fbr_pos_id'),
            'email' => $this->input('email') ?? $this->input('user.email'),
            'password' => $this->input('password') ?? $this->input('user.password'),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tenant_name' => ['required', 'string', 'max:255'],
            'ntn' => ['required', 'string', 'max:50'],
            'strn' => ['required', 'string', 'max:50'],
            'province' => ['required', 'string', 'in:punjab,sindh,kp,balochistan'],
            'fbr_pos_id' => ['nullable', 'string', 'max:100'],
            'user_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
        ];
    }
}
