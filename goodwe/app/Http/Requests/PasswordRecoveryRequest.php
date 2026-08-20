<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PasswordRecoveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'exists:users,email'],
            'phone' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => __('validation.email_required'),
            'email.email' => __('validation.email_invalid'),
            'email.exists' => __('auth.email_not_registered'),
            'phone.required' => __('validation.phone_required'),
        ];
    }
}
