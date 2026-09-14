<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RedefinirRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => 'required|email|exists:users,email',
            'password' => 'required|min:6',
            'password_confirmation' => 'required|same:password',
        ];
    }

    public function messages(): array
    {
        return [
            'email.exists' => __('auth.email_not_registered'),
            'password.required' => __('auth.password_required'),
            'password.min' => __('auth.password_min'),
            'password_confirmation.required' => __('password_confirmation_required'),
            'password_confirmation.same' => __('password_confirmation_same'),
        ];
    }
}
